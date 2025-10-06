<?php

namespace App\Services;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use App\Models\UserAgent;
use Carbon\Carbon;
use App\Http\Resources\AgentResource;
use Exception;

class UserAgentService
{
    public static function getUserAgent($userId)
    {
        $agent = UserAgent::where('user_id', $userId)
            ->first();
        if ($agent) {
            return ServiceResponse::success(self::refreshAgent($agent));
        }

        return ServiceResponse::notFound();
    }

    public static function refreshAgent(UserAgent $agent): ?UserAgent
    {
        if (
            !$agent->access_token ||
            !$agent->token_updated_at ||
            $agent->token_updated_at->diffInHours(Carbon::now()) > 15
        ) {
            $response = TdmsService::getAgentToken(
                username: $agent->email,
                password: $agent->password,
            );

            if ($response->isError() || !$response->data) {
                return null;
            }

            $agent->update([
                "access_token" => $response->data['access_token'],
                "subsystem_type" => $response->data['subSystemType'],
                "points_balance" => $response->data['pointsBalance'],
                "points_available" => $response->data['availableBalance'],
                "points_multiplier" => $response->data['pointsMultiplier'],
                "token_updated_at" => Carbon::now()
            ]);
            $agent->refresh();
        }

        return $agent;
    }

    public static function getDefaultAgentToken()
    {
        if (app()->bound('platform') && app('platform') == AgentBranchCode::PETERPANS) {
            $branchCode = config('vars.ptx_agent_branch_code');
            $email = config('vars.ptx_agent_email');
            $password = config('vars.ptx_agent_password');
        } else {
            $branchCode = config('vars.default_agent_branch_code');
            $email = config('vars.default_agent_email');
            $password = config('vars.default_agent_password');
        }

        $defaultAgent = UserAgent::where('email', $email)->first();
        if (!$defaultAgent) {
            $defaultAgent = UserAgent::create([
                "branch_code" => $branchCode,
                "email" => $email,
                "password" => $password,
                "subsystem_type" => "FTA"
            ]);
        }

        $refreshedAgent = self::refreshAgent($defaultAgent);
        if (!$refreshedAgent) {
            $errorMessage = 'Unable to get default agent token';
            Logger::error($errorMessage);
            return ServiceResponse::badRequest($errorMessage);
        }

        return ServiceResponse::success(["access_token" => $refreshedAgent->access_token]);
    }

    public static function getUserAgentToken(UserAgent $userAgent): ServiceResponse
    {
        $refreshedAgent = self::refreshAgent($userAgent);
        if (!$refreshedAgent) {
            return ServiceResponse::badRequest(
                message: 'Unable to get new access token',
                data: [
                    "errorCode" => 4001,
                    "errorMessage" => 'Invalid agent credential'
                ]
            );
        }

        return ServiceResponse::success(["access_token" => $refreshedAgent->access_token]);
    }

    public static function getUserAgentIfExistsElseDefault($userId): ServiceResponse
    {
        $userAgent = new UserAgent();
        $activeAgent = $userAgent->getActiveAgent($userId);
        if ($activeAgent) {
            $agent = $activeAgent;
        } else {
            $agent = $userAgent->getDefaultAgent();
        }

        $refreshAgent = self::refreshAgent($agent);
        if (!$refreshAgent) {
            return ServiceResponse::badRequest('Could not get agent');
        }

        return ServiceResponse::success($refreshAgent);
    }

    public static function getBranchCodeOfAgent($agentToken)
    {
        $bookingReferenceResponse = TdmsService::getBookingRefrence($agentToken);
        if (!$bookingReferenceResponse) {
            return null;
        }

        return substr($bookingReferenceResponse, 0, 3);
    }

    public static function addUserAgent(
        $userId,
        $username,
        $password,
        ?string $branchCode,
        ?int $referralSourceId,
        string $subSystemType = 'FIT'
    ): ServiceResponse {
        $response = TdmsService::getAgentToken($username, $password);
        if ($response->isError() || !$response->data) {
            return ServiceResponse::badRequest('Invalid agent credential');
        }

        $agentDetail = $response->data;

        if (!$branchCode) {
            $branchCode = self::getBranchCodeOfAgent($agentDetail['access_token']);
        }

        if (!$branchCode) {
            return ServiceResponse::badRequest('Could not get branch code. Unable to add agent.');
        }

        try {
            $agentDetail["email"] = $username;
            $agentDetail["password"] = $password;
            $agentDetail["user_id"] = $userId;
            $agentDetail["status"] = 'ok';
            $agentDetail['type'] = 'integration';
            $agentDetail['is_active'] = true;
            $agentDetail['branch_code'] = $branchCode;
            $agentDetail['referral_source_id'] = $referralSourceId;
            $agentDetail['subsystem_type'] = $subSystemType;

            $userAgent = UserAgent::create($agentDetail);
            $userAgentData = AgentResource::userAgentDetails($userAgent);

            return ServiceResponse::success($userAgentData);
        } catch (Exception $e) {
            Logger::error('Exception while adding agent', $e);
            return ServiceResponse::badRequest('Could not add agent');
        }
    }

    public static function updateUserAgent(UserAgent $agent, string $username, string $password): ServiceResponse
    {
        $response = TdmsService::getAgentToken($username, $password);

        if ($response->isError() || !$response->data) {
            return ServiceResponse::badRequest('Invalid credentials.');
        }

        $newAgentDetail = $response->data;
        $newBranchCode = UserAgentService::getBranchCodeOfAgent($newAgentDetail['access_token']);
        if (!$newBranchCode) {
            return ServiceResponse::badRequest('Could not get branch code. Unable to update agent.');
        }

        if (!($newBranchCode === $agent->branch_code)) {
            return ServiceResponse::badRequest('Old and new branch code mismatch. Unable to update agent.');
        }

        $agent->update($newAgentDetail);
        return ServiceResponse::success();
    }

    public static function upgradeToCommission(UserAgent $agent, array $data): ServiceResponse
    {
        $newAgentDetail = [
            "subsystem_type" => $data['subSystemType'],
            'points_balance' => $data['pointsBalance'],
            'points_available' => $data['availableBalance'],
            'points_multiplier' => $data['pointsMultiplier'],
        ];

        $agent->update($newAgentDetail);
        return ServiceResponse::success($agent);
    }

    public static function unlinkAgent($agent)
    {
        $agent->update([
            'is_active' => false,
            'is_deleted' => true,
        ]);

        return ServiceResponse::success();
    }

    public static function getUserAgentById($userAgentId)
    {
        $userAgent = UserAgent::where('id', $userAgentId)->first();
        if (!$userAgent) {
            return ServiceResponse::badRequest(
                message: "Agent not found"
            );
        }

        $refreshedAgent = UserAgentService::refreshAgent($userAgent);
        return ServiceResponse::success(
            data: $refreshedAgent
        );
    }

    public static function getUserAgentByBranch($agentBranchCode)
    {
        $userAgent = UserAgent::where('branch_code', $agentBranchCode)->first();
        if (!$userAgent) {
            return ServiceResponse::badRequest(
                message: "Agent not found"
            );
        }

        $refreshedAgent = UserAgentService::refreshAgent($userAgent);
        return ServiceResponse::success(
            data: $refreshedAgent
        );
    }

    public static function updateUserAgentPoints($agent, $data)
    {
        try {
            $agent->points_balance = $data['pointsBalance'];
            $agent->points_available = $data['availableBalance'];
            $agent->points_multiplier = $data['pointsMultiplier'];
            $agent->save();

            return ServiceResponse::success(
                message: "Agent details updated successfully",
                data: $agent
            );
        } catch (Exception $e) {
            Logger::error('Error updating user agent points', $e);
        }
    }
}
