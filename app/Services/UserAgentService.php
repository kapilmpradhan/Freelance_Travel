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
        Logger::debug('Getting user agent', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'get_user_agent_start',
        ]);

        $agent = UserAgent::where('user_id', $userId)
            ->first();
        if ($agent) {
            Logger::debug('User agent found', [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'agent_id' => $agent->id,
                'action' => 'get_user_agent_found',
            ]);
            return ServiceResponse::success(self::refreshAgent($agent));
        }

        Logger::debug('User agent not found', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'get_user_agent_not_found',
        ]);
        return ServiceResponse::notFound();
    }

    public static function getUserAgentByEmail($email)
    {
        $agent = UserAgent::where('email', $email)
            ->first();
        if ($agent) {
            return ServiceResponse::success(self::refreshAgent($agent));
        }
        return ServiceResponse::notFound();
    }

    public static function getDefaultAgent()
    {
        Logger::debug('Getting default agent', [
            'log_file' => config('logging.log_files.agent'),
            'action' => 'get_default_agent_start',
        ]);

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
            Logger::debug('Creating default agent', [
                'log_file' => config('logging.log_files.agent'),
                'branch_code' => $branchCode,
                'action' => 'create_default_agent',
            ]);
            $defaultAgent = UserAgent::create([
                "branch_code" => $branchCode,
                "email" => $email,
                "password" => $password,
                "subsystem_type" => "FTA"
            ]);
        }

        $refreshedAgent = self::refreshAgent($defaultAgent);
        if (!$refreshedAgent) {
            $errorMessage = 'Unable to get default agent';
            Logger::error($errorMessage, data: [
                'log_file' => config('logging.log_files.agent'),
                'branch_code' => $branchCode,
                'action' => 'get_default_agent_failed',
            ]);
            return ServiceResponse::badRequest($errorMessage);
        }

        Logger::debug('Default agent retrieved', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $refreshedAgent->id,
            'branch_code' => $branchCode,
            'action' => 'get_default_agent_success',
        ]);
        return ServiceResponse::success($refreshedAgent);
    }

    public static function refreshAgent(UserAgent $agent): ?UserAgent
    {
        if (
            !$agent->access_token ||
            !$agent->token_updated_at ||
            $agent->token_updated_at->diffInHours(Carbon::now()) > 15
        ) {
            Logger::debug('Refreshing agent token', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'refresh_agent_token_start',
            ]);

            $response = TdmsService::getAgentToken(
                username: $agent->email,
                password: $agent->password,
            );

            if ($response->isError() || !$response->data) {
                Logger::error('Failed to refresh agent token', data: [
                    'log_file' => config('logging.log_files.agent'),
                    'agent_id' => $agent->id,
                    'action' => 'refresh_agent_token_failed',
                ]);
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

            Logger::debug('Agent token refreshed', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'refresh_agent_token_success',
            ]);
        }

        return $agent;
    }

    public static function getDefaultAgentToken()
    {
        Logger::debug('Getting default agent token', [
            'log_file' => config('logging.log_files.agent'),
            'action' => 'get_default_agent_token_start',
        ]);

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
            Logger::error($errorMessage, data: [
                'log_file' => config('logging.log_files.agent'),
                'branch_code' => $branchCode,
                'action' => 'get_default_agent_token_failed',
            ]);
            return ServiceResponse::badRequest($errorMessage);
        }

        Logger::debug('Default agent token retrieved', [
            'log_file' => config('logging.log_files.agent'),
            'branch_code' => $branchCode,
            'action' => 'get_default_agent_token_success',
        ]);
        return ServiceResponse::success(["access_token" => $refreshedAgent->access_token]);
    }

    public static function getUserAgentToken(UserAgent $userAgent): ServiceResponse
    {
        Logger::debug('Getting user agent token', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $userAgent->id,
            'action' => 'get_user_agent_token_start',
        ]);

        $refreshedAgent = self::refreshAgent($userAgent);
        if (!$refreshedAgent) {
            Logger::error('Failed to get user agent token', data: [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $userAgent->id,
                'action' => 'get_user_agent_token_failed',
            ]);
            return ServiceResponse::badRequest(
                message: 'Unable to get new access token',
                data: [
                    "errorCode" => 4001,
                    "errorMessage" => 'Invalid agent credential'
                ]
            );
        }

        Logger::debug('User agent token retrieved', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $userAgent->id,
            'action' => 'get_user_agent_token_success',
        ]);
        return ServiceResponse::success(["access_token" => $refreshedAgent->access_token]);
    }

    public static function getUserAgentIfExistsElseDefault($userId): ServiceResponse
    {
        Logger::debug('Getting user agent or default', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'get_user_agent_or_default_start',
        ]);

        $userAgent = new UserAgent();
        $activeAgent = $userAgent->getActiveAgent($userId);
        if ($activeAgent) {
            $agent = $activeAgent;
            Logger::debug('Using user active agent', [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'agent_id' => $agent->id,
                'action' => 'using_user_active_agent',
            ]);
        } else {
            $agent = self::getDefaultAgent()->data;
            Logger::debug('Using default agent', [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'action' => 'using_default_agent',
            ]);
        }

        $refreshAgent = self::refreshAgent($agent);
        if (!$refreshAgent) {
            Logger::error('Could not get agent', data: [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'action' => 'get_user_agent_or_default_failed',
            ]);
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
        Logger::debug('Adding user agent', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'branch_code' => $branchCode,
            'action' => 'add_user_agent_start',
        ]);

        $response = TdmsService::getAgentToken($username, $password);
        if ($response->isError() || !$response->data) {
            Logger::debug('Invalid agent credentials', [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'action' => 'add_user_agent_invalid_credentials',
            ]);
            return ServiceResponse::badRequest('Invalid agent credential');
        }

        $agentDetail = $response->data;

        if (!$branchCode) {
            $branchCode = self::getBranchCodeOfAgent($agentDetail['access_token']);
        }

        if (!$branchCode) {
            Logger::error('Could not get branch code', data: [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'action' => 'add_user_agent_no_branch_code',
            ]);
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

            Logger::debug('User agent added', [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'agent_id' => $userAgent->id,
                'branch_code' => $branchCode,
                'action' => 'add_user_agent_success',
            ]);
            return ServiceResponse::success($userAgentData);
        } catch (Exception $e) {
            Logger::error('Exception while adding agent', $e, data: [
                'log_file' => config('logging.log_files.agent'),
                'user_id' => $userId,
                'action' => 'add_user_agent_exception',
            ]);
            return ServiceResponse::badRequest('Could not add agent');
        }
    }

    public static function updateUserAgent(UserAgent $agent, string $username, string $password): ServiceResponse
    {
        Logger::debug('Updating user agent', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'update_user_agent_start',
        ]);

        $response = TdmsService::getAgentToken($username, $password);

        if ($response->isError() || !$response->data) {
            Logger::debug('Invalid credentials for agent update', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_user_agent_invalid_credentials',
            ]);
            return ServiceResponse::badRequest('Invalid credentials.');
        }

        $newAgentDetail = $response->data;
        $newBranchCode = UserAgentService::getBranchCodeOfAgent($newAgentDetail['access_token']);
        if (!$newBranchCode) {
            Logger::error('Could not get branch code for agent update', data: [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_user_agent_no_branch_code',
            ]);
            return ServiceResponse::badRequest('Could not get branch code. Unable to update agent.');
        }

        if (!($newBranchCode === $agent->branch_code)) {
            Logger::debug('Branch code mismatch for agent update', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'old_branch_code' => $agent->branch_code,
                'new_branch_code' => $newBranchCode,
                'action' => 'update_user_agent_branch_mismatch',
            ]);
            return ServiceResponse::badRequest('Old and new branch code mismatch. Unable to update agent.');
        }

        $agent->update($newAgentDetail);

        Logger::debug('User agent updated', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'update_user_agent_success',
        ]);
        return ServiceResponse::success();
    }

    public static function upgradeToCommission(UserAgent $agent, array $data): ServiceResponse
    {
        Logger::debug('Upgrading agent to commission', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'subsystem_type' => $data['subSystemType'],
            'action' => 'upgrade_to_commission_start',
        ]);

        $newAgentDetail = [
            "subsystem_type" => $data['subSystemType'],
            'points_balance' => $data['pointsBalance'],
            'points_available' => $data['availableBalance'],
            'points_multiplier' => $data['pointsMultiplier'],
        ];

        $agent->update($newAgentDetail);

        Logger::debug('Agent upgraded to commission', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'upgrade_to_commission_success',
        ]);
        return ServiceResponse::success($agent);
    }

    public static function unlinkAgent($agent)
    {
        Logger::debug('Unlinking agent', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'unlink_agent_start',
        ]);

        $agent->update([
            'is_active' => false,
            'is_deleted' => true,
        ]);

        Logger::debug('Agent unlinked', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'unlink_agent_success',
        ]);
        return ServiceResponse::success();
    }

    public static function getUserAgentById($userAgentId)
    {
        Logger::debug('Getting user agent by ID', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $userAgentId,
            'action' => 'get_user_agent_by_id_start',
        ]);

        $userAgent = UserAgent::where('id', $userAgentId)->first();
        if (!$userAgent) {
            Logger::debug('User agent not found by ID', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $userAgentId,
                'action' => 'get_user_agent_by_id_not_found',
            ]);
            return ServiceResponse::badRequest(
                message: "Agent not found"
            );
        }

        $refreshedAgent = UserAgentService::refreshAgent($userAgent);

        Logger::debug('User agent retrieved by ID', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $userAgentId,
            'action' => 'get_user_agent_by_id_success',
        ]);
        return ServiceResponse::success(
            data: $refreshedAgent
        );
    }

    public static function getUserAgentByBranch($agentBranchCode)
    {
        Logger::debug('Getting user agent by branch', [
            'log_file' => config('logging.log_files.agent'),
            'branch_code' => $agentBranchCode,
            'action' => 'get_user_agent_by_branch_start',
        ]);

        $userAgent = UserAgent::where('branch_code', $agentBranchCode)->first();
        if (!$userAgent) {
            Logger::debug('User agent not found by branch', [
                'log_file' => config('logging.log_files.agent'),
                'branch_code' => $agentBranchCode,
                'action' => 'get_user_agent_by_branch_not_found',
            ]);
            return ServiceResponse::badRequest(
                message: "Agent not found"
            );
        }

        $refreshedAgent = UserAgentService::refreshAgent($userAgent);

        Logger::debug('User agent retrieved by branch', [
            'log_file' => config('logging.log_files.agent'),
            'branch_code' => $agentBranchCode,
            'agent_id' => $userAgent->id,
            'action' => 'get_user_agent_by_branch_success',
        ]);
        return ServiceResponse::success(
            data: $refreshedAgent
        );
    }

    public static function updateUserAgentPoints($agent, $data)
    {
        Logger::debug('Updating user agent points', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'update_agent_points_start',
        ]);

        try {
            $agent->points_balance = $data['pointsBalance'];
            $agent->points_available = $data['availableBalance'];
            $agent->points_multiplier = $data['pointsMultiplier'];
            $agent->save();

            Logger::debug('User agent points updated', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_agent_points_success',
            ]);
            return ServiceResponse::success(
                message: "Agent details updated successfully",
                data: $agent
            );
        } catch (Exception $e) {
            Logger::error('Error updating user agent points', $e, data: [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_agent_points_exception',
            ]);
        }
    }

    public static function updateAgentBankDetails($agent, $data)
    {
        Logger::debug('Updating agent bank details', [
            'log_file' => config('logging.log_files.agent'),
            'agent_id' => $agent->id,
            'action' => 'update_agent_bank_details_start',
        ]);

        try {
            $agent->bank_bsb = $data['bankBsb'];
            $agent->bank_account = $data['bankAccount'];
            $agent->bank_country_short_code = $data['bankCountryShortCode'];
            $agent->business_number = $data['businessNumber'];
            $agent->trading_name = $data['tradingName'];
            $agent->save();

            Logger::debug('Agent bank details updated', [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_agent_bank_details_success',
            ]);
            return ServiceResponse::success(
                message: "Agent bank details updated successfully",
                data: $agent
            );
        } catch (Exception $e) {
            Logger::error('Error updating user agent bank details', $e, data: [
                'log_file' => config('logging.log_files.agent'),
                'agent_id' => $agent->id,
                'action' => 'update_agent_bank_details_exception',
            ]);
            throw new ServiceException('Could not update agent bank details');
        }
    }

    public static function getReferralSource($referredToBranch, $referredByBranch)
    {
        Logger::debug('Getting referral source', [
            'log_file' => config('logging.log_files.agent'),
            'referred_to_branch' => $referredToBranch,
            'referred_by_branch' => $referredByBranch,
            'action' => 'get_referral_source_start',
        ]);

        $agentResponse = self::getUserAgentByBranch($referredToBranch);
        if ($agentResponse->isError()) {
            return $agentResponse;
        }

        $referredToAgent = $agentResponse->data;

        $referralSourcesResponse = TdmsService::getReferralSourcesOfAgent($referredToAgent->access_token);
        if ($referralSourcesResponse->isError()) {
            Logger::debug('Failed to get referral sources', [
                'log_file' => config('logging.log_files.agent'),
                'referred_to_branch' => $referredToBranch,
                'action' => 'get_referral_source_failed',
            ]);
            return $referralSourcesResponse;
        }
        $referralSource = collect($referralSourcesResponse->data)
            ->first(fn ($source) => $source['referralName'] === $referredByBranch);

        if (empty($referralSource)) {
            Logger::debug('Referral source not found', [
                'log_file' => config('logging.log_files.agent'),
                'referred_to_branch' => $referredToBranch,
                'referred_by_branch' => $referredByBranch,
                'action' => 'referral_source_not_found',
            ]);
            return ServiceResponse::badRequest('Referral source not found');
        }

        Logger::debug('Referral source retrieved', [
            'log_file' => config('logging.log_files.agent'),
            'referred_to_branch' => $referredToBranch,
            'referred_by_branch' => $referredByBranch,
            'action' => 'get_referral_source_success',
        ]);
        return ServiceResponse::success($referralSource);
    }
}
