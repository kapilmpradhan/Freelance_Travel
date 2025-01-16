<?php

namespace App\Http\Controllers\Api;

use App\Models\UserAgent;
use Illuminate\Http\Request;
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
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        $addUserAgentResponse = UserAgentService::addUserAgent(
            userId: $userId,
            username: $data['username'],
            password: $data['password']
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
            return $this->sendError('User agent not integrated');
        }

        $getUserAgentResponse = UserAgentService::getUserAgentToken($userId, $agent);
        return $this->sendResponseFromService($getUserAgentResponse);
    }

    public function getDefaultAgentToken(Request $request)
    {
        $getdefaultAgentTokenResponse = UserAgentService::getDefaultAgentToken();
        return $this->sendResponseFromService($getdefaultAgentTokenResponse);
    }
}
