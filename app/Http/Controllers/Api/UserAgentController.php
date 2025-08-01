<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\UserAgent;
use App\Services\TdmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AgentResource;
use App\Services\UserAgentService;

class UserAgentController extends BaseController
{
    public function addUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;
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

        $getUserAgentResponse = UserAgentService::getUserAgentToken($userId, $agent);
        return $this->sendResponseFromService($getUserAgentResponse);
    }

    public function getDefaultAgentToken(Request $request)
    {
        $getdefaultAgentTokenResponse = UserAgentService::getDefaultAgentToken($request->agentBranchCode);
        return $this->sendResponseFromService($getdefaultAgentTokenResponse);
    }

    public function unlinkUserAgent(Request $request, UserAgent $userAgent)
    {
        $userId = $request->user->uuid;
        $agent = $userAgent->getActiveAgent($userId);
        if (!$agent) {
            return $this->sendError('No agent integrated');
        }

        $unkinkAgentResponse = UserAgentService::unlinkAgent($agent);

        return $this->sendResponseFromService($unkinkAgentResponse);
    }

    public function upgradeToAgent(Request $request, TdmsService $tdmsService): JsonResponse
    {
        $user = $request->user; /** @var User $user */

        $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($user->uuid);

        if ($getAgentResponse->isError()) {
            return $this->sendResponseFromService($getAgentResponse);
        }

        $responseData = $getAgentResponse->data;

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

        return $this->sendResponseFromService($addUserAgentResponse);
    }
}
