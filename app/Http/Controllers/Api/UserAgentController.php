<?php

namespace App\Http\Controllers\Api;

use App\Logging\Logger;
use App\Models\User;
use App\Models\UserAgent;
use App\Services\TdmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AgentResource;
use App\Services\ServiceException;
use App\Services\UserAgentService;
use App\Services\UserCacheService;
use Laravel\Pennant\Feature;

class UserAgentController extends BaseController
{
    public function addUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;

        Logger::debug('Add user agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'add_user_agent_request',
        ]);

        if ($userAgent->getActiveAgent($userId)) {
            return $this->sendError('Agent already integrated');
        }

        $data = $request->all();
        $validate = Validator::make($data, ['username' => 'required|email', 'password' => 'required|string']);
        if ($validate->fails()) {
            return $this->sendError('Error occurred', $validate->errors(), 400);
        }

        $addUserAgentResponse = UserAgentService::addUserAgent(
            userId: $userId,
            username: $data['username'],
            password: $data['password'],
            branchCode: null,
            referralSourceId: null
        );

        return $this->sendResponseFromService($addUserAgentResponse);
    }

    public function getUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;

        Logger::debug('Get user agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'get_user_agent_request',
        ]);

        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('Needs agent integration');
        }

        $agentData = AgentResource::userAgentDetails($agent);

        return $this->sendResponse("User agent details", $agentData);
    }

    public function updateUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;

        Logger::debug('Update user agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'update_user_agent_request',
        ]);

        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('No agent integrated');
        }

        $data = $request->all();
        $validate = Validator::make($data, ['username' => 'required|email', 'password' => 'required|string']);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        $updateUserAgentResponse = UserAgentService::updateUserAgent(
            agent: $agent,
            username: $data['username'],
            password: $data['password']
        );

        return $this->sendResponseFromService($updateUserAgentResponse);
    }

    public function getUserAgentToken(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;
        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('No agent integrated');
        }

        $getUserAgentResponse = UserAgentService::getUserAgentToken($agent);
        return $this->sendResponseFromService($getUserAgentResponse);
    }

    public function getAndUpdateUserAgentPoints(Request $request, UserAgent $userAgent): JsonResponse
    {
        $userId = $request->user->uuid;
        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('No agent integrated');
        }

        $getUserAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);

        if ($getUserAgentResponse->isError()) {
            return $this->sendResponseFromService($getUserAgentResponse);
        }

        $userAgent = $getUserAgentResponse->data; /** @var UserAgent $userAgent */

        $agentDetailsResponse = TdmsService::getAgentDetails($userAgent->access_token);
        if ($agentDetailsResponse->isError()) {
            return $this->sendResponseFromService($agentDetailsResponse);
        }

        UserAgentService::updateUserAgentPoints(
            agent: $userAgent,
            data: $agentDetailsResponse->data
        );
        return $this->sendResponseFromService(TdmsService::getAgentDetails($userAgent->access_token));
    }

    public function getDefaultAgentToken(Request $request)
    {
        $getdefaultAgentTokenResponse = UserAgentService::getDefaultAgentToken();
        return $this->sendResponseFromService($getdefaultAgentTokenResponse);
    }

    public function getCommissionReport(
        Request $request,
        UserAgent $userAgent,
        TdmsService $tdmsService
    ): JsonResponse {
        $userId = $request->user->uuid;
        $agent = $userAgent->getActiveAgent($userId);

        if (!$agent) {
            return $this->sendError('Have not yet upgraded to a points agent.');
        }

        $data = $request->all();
        $validate = Validator::make($data, [
            'startDate' => 'required|date_format:Y-m-d',
            'endDate' => 'required|date_format:Y-m-d',
            'onlyAvailablePoints' => 'nullable|boolean',
            'untilTodayOnly' => 'nullable|boolean',
        ]);

        if ($validate->fails()) {
            return $this->sendError('Error occurred', $validate->errors());
        }

        $getUserAgentResponse = UserAgentService::getUserAgentToken($agent);

        if ($getUserAgentResponse->isError()) {
            return $this->sendResponseFromService($getUserAgentResponse);
        }

        $commissionReportResponse = $tdmsService->getCommissionReport(
            $getUserAgentResponse->data['access_token'],
            new Carbon($data['startDate']),
            new Carbon($data['endDate']),
            $data['onlyAvailablePoints'] ?? false,
            $data['untilTodayOnly'] ?? false,
        );

        return $this->sendResponseFromService($commissionReportResponse);
    }

    public function unlinkUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;

        Logger::debug('Unlink user agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $userId,
            'action' => 'unlink_user_agent_request',
        ]);

        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('No agent integrated');
        }

        $unkinkAgentResponse = UserAgentService::unlinkAgent($agent);

        return $this->sendResponseFromService($unkinkAgentResponse);
    }

    public function upgradeToAgent(Request $request, TdmsService $tdmsService): JsonResponse
    {
        $referrerEmail = $request->get('referrer');
        $user = $request->user; /** @var User $user */

        Logger::debug('Upgrade to agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $user->uuid,
            'action' => 'upgrade_to_agent_request',
        ]);

        if (Feature::for($user)->active('tester')) {
            return $this->sendError('This test account cannot be upgraded to an agent.');
        }

        $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($user->uuid);

        if ($getAgentResponse->isError()) {
            return $this->sendResponseFromService($getAgentResponse);
        }

        $responseData = $getAgentResponse->data;

        if (!$referrerEmail) {
            // need to lookup order history for this user and find where they've done the most
            $orderHistoryResponse = TdmsService::customerOrderHistory(
                $responseData->access_token,
                $user->email,
                now()->subMonths(config('vars.agent_upgrade_order_months_history'))
            );

            $topBranchCode = null;
            if (count($orderHistoryResponse->data ?? []) >= 0) {
                $orderHistory = collect($orderHistoryResponse->data)
                    ->reject(fn ($order) => $order['salesBranchCode'] === config('vars.default_agent_branch_code'))
                    ->groupBy('salesBranchCode')
                    ->map(fn (Collection $orders, $branchCode) => $orders->sum('totalAmountExcludeCCFee'));
                $totalsByBranch = $orderHistory->sortDesc();
                $topBranchCode = $totalsByBranch->keys()->first();
            }
        } else {
            $referrer = UserAgent::where('email', $referrerEmail)->first();
            $topBranchCode = $referrer ? $referrer->branch_code : null;
        }

        $upgradeToAgentResponse = $tdmsService->upgradeToAgent(
            $user,
            $responseData->access_token,
            $topBranchCode
        );

        if ($upgradeToAgentResponse->isError()) {
            return $this->sendResponseFromService($upgradeToAgentResponse);
        }

        $data = $upgradeToAgentResponse->data;

        $addUserAgentResponse = UserAgentService::addUserAgent(
            userId: $user->uuid,
            username: $data['emailAddress'],
            password: $data['password'],
            branchCode: $data['agentCode'],
            referralSourceId: $data['referralSourceId'] ?? null
        );

        UserCacheService::removeAllCachedData();

        return $this->sendResponseFromService($addUserAgentResponse);
    }

    public function upgradeToCommissionAgent(Request $request): JsonResponse
    {
        $user = $request->user; /** @var User $user */

        Logger::debug('Upgrade to commission agent request', [
            'log_file' => config('logging.log_files.agent'),
            'user_id' => $user->uuid,
            'action' => 'upgrade_to_commission_agent_request',
        ]);

        if (Feature::for($user)->active('tester')) {
            return $this->sendError('This test account cannot be upgraded to an agent.');
        }

        $data = $request->all();
        $validator = Validator::make($data, [
            'bankBsb' => 'required|string',
            'bankAccount' => 'required|string',
            'bankCountryShortCode' => 'required|string',
            'businessNumber' => 'required|string',
            'tradingName' => 'required|string',
        ]);

        $validator->after(function ($validator) use ($data) {
            $bankAccount = $data['bankAccount'] ?? '';
            $countryCode = strtoupper($data['bankCountryShortCode'] ?? '');
            $accountLength = strlen($bankAccount);

            if ($countryCode === 'AU') {
                if ($accountLength < 6 || $accountLength > 9) {
                    $validator->errors()->add('bankAccount', 'Account number must be between 6 and 8 digits for AU.');
                }
            } elseif ($countryCode === 'NZ') {
                if ($accountLength < 6 || $accountLength > 10) {
                    $validator->errors()->add('bankAccount', 'Account number must be between 6 and 9 digits for NZ.');
                }
            } else {
                if ($accountLength < 6 || $accountLength > 19) {
                    $validator->errors()->add('bankAccount', 'Account number must be between 6 and 19 digits.');
                }
            }
        });

        if ($validator->fails()) {
            return $this->sendError('Error occurred', $validator->errors(), 400);
        }

        $data = $validator->validated();

        $getAgentResponse = UserAgentService::getUserAgent($user->uuid);

        if ($getAgentResponse->isError()) {
            return $this->sendResponseFromService($getAgentResponse);
        }

        // Update agent bank details in db first
        $agent = $getAgentResponse->data;
        try {
            $updateAgentBankDetailsResponse = UserAgentService::updateAgentBankDetails(
                agent: $agent,
                data: $data
            );

            if ($updateAgentBankDetailsResponse->isError()) {
                return $this->sendResponseFromService($updateAgentBankDetailsResponse);
            }
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }

        // Now update bank details in TDMS
        $agent = $updateAgentBankDetailsResponse->data;
        try {
            $updateAgentResponse = TdmsService::updateAgentDetails($agent);
            if ($updateAgentResponse->isError()) {
                return $this->sendResponseFromService($updateAgentResponse);
            }
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }

        // Now upgrade to commission agent in TDMS
        $upgradeToCommissionAgentResponse = TdmsService::upgradeToCommissionAgent($agent);

        if ($upgradeToCommissionAgentResponse->isError()) {
            return $this->sendResponseFromService($upgradeToCommissionAgentResponse);
        }

        $data = $upgradeToCommissionAgentResponse->data;

        $upgradeAgent = UserAgentService::upgradeToCommission(
            agent: $agent,
            data: $data
        );

        return $this->sendResponseFromService($upgradeAgent);
    }

    public function upgradeToCommissionAgentWebhook(Request $request): JsonResponse
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'agentEmail' => 'required|email',
            'bankBsb' => 'required|string',
            'bankAccount' => 'required|string',
            'bankCountryShortCode' => 'required|string',
            'businessNumber' => 'required|string',
            'tradingName' => 'string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Error occurred', $validator->errors(), 400);
        }

        $data = $validator->validated();

        $getAgentResponse = UserAgentService::getUserAgentByEmail($data['agentEmail']);

        if ($getAgentResponse->isError()) {
            return $this->sendResponseFromService($getAgentResponse);
        }

        // Update agent bank details in db first
        $agent = $getAgentResponse->data;
        try {
            $updateAgentBankDetailsResponse = UserAgentService::updateAgentBankDetails(
                agent: $agent,
                data: $data
            );

            if ($updateAgentBankDetailsResponse->isError()) {
                return $this->sendResponseFromService($updateAgentBankDetailsResponse);
            }
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }

        // Fetch agent details from TDMS to ensure bank details are up to date
        $getAgentDetailsResponse = TdmsService::getAgentDetails($agent->access_token);

        if ($getAgentDetailsResponse->isError()) {
            return $this->sendResponseFromService($getAgentDetailsResponse);
        }

        $data = $getAgentDetailsResponse->data;
        UserAgentService::upgradeToCommission(
            agent: $agent,
            data: $data
        );

        return $this->sendResponse('Agent upgraded to commission agent via webhook');
    }
}
