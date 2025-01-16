<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\AgentToken;
use App\Models\UserAgent;
use App\Models\Agent;
use Carbon\Carbon;
use App\Services\TdmsService;
use App\Http\Resources\AgentResource;
use App\Models\User;

class UserAgentService
{
    public static function refreshAgent($agent)
    {
        if (
            !$agent->access_token
            || $agent->updated_at->diffInHours(Carbon::now()) > 21
        ) {
            $new_token = TdmsService::getAgentToken(
                username: $agent->email,
                password: $agent->password,
            );

            if (!$new_token) {
                return null;
            }
            $agent->update(["access_token" => $new_token['access_token']]);
            $agent->refresh();
        }

        return $agent;
    }

    public static function getDefaultAgentToken()
    {
        $defaultAgent = UserAgent::where('email', config('vars.default_agent_email'))->first();
        if (!$defaultAgent) {
            $defaultAgent = UserAgent::create([
                "branch_code" => config('vars.default_agent_branch_code'),
                "email" => config('vars.default_agent_email'),
                "password" => config('vars.default_agent_password')
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

    public static function getUserAgentToken($userId, UserAgent $userAgent)
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

    public static function getBranchCodeOfAgent($agentToken)
    {
        $bookingReferenceResponse = TdmsService::getBookingRefrence($agentToken);
        if (!$bookingReferenceResponse) {
            return null;
        }

        return substr($bookingReferenceResponse, 0, 3);
    }

    public static function addUserAgent($userId, $username, $password)
    {
        $agentDetail = TdmsService::getAgentToken($username, $password);
        if (!$agentDetail) {
            return ServiceResponse::badRequest('Invalid agent credential');
        }

        $branchCode = self::getBranchCodeOfAgent($agentDetail['access_token']);
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

            $userAgent = UserAgent::create($agentDetail);
            $userAgentData = AgentResource::userAgentDetails($userAgent);

            return ServiceResponse::success($userAgentData);
        } catch (\Exception $e) {
            Logger::error('Exception while adding agent', $e);
            return ServiceResponse::badRequest('Could not add agent');
        }
    }

    public static function updateUserAgent($agent, $username, $password)
    {
        $newAgentDetail = TdmsService::getAgentToken($username, $password);

        if (!$newAgentDetail) {
            return ServiceResponse::badRequest('Invalid credentials.');
        }

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
}
