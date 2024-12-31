<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Models\UserAgent;
use Illuminate\Http\Request;
use App\Services\AgentTokenService;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AgentResource;
use App\Logging\Logger;
use App\Models\Agent;

class UserAgentController extends BaseController
{
    public function addUserAgent(Request $request)
    {
        $data = $request->all();
        $data['user_id'] = $request->user->uuid;
        $validate = Validator::make($data, ['username' => 'required|email', 'password' => 'required|string']);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }
        $userAgentExists = UserAgent::where('email', $data['username'])
                                    ->where('user_id', $request->user->uuid)
                                    ->first();
        if ($userAgentExists) {
            return $this->sendError('Agent with that username already exists');
        }

        $agentDetail = AgentTokenService::getAgentToken($data['username'], $data['password']);
        if (!$agentDetail) {
            return $this->sendError('Agent token info', [
                "errorCode" => "100004",
                "errorMessage" => "Invalid agent credential"
            ], 401);
        }

        try {
            $agentDetail["email"] = $data["username"];
            $agentDetail["password"] = $data["password"];
            $agentDetail["user_id"] = $request->user->uuid;
            $agentDetail["status"] = 'ok';
            $agentDetail['type'] = 'integration';

            $userAgent = UserAgent::create($agentDetail);

            $agentResource = AgentResource::agentOverview($userAgent);

            return $this->sendResponse('Agent added', $agentResource, 201);
        } catch (Exception $e) {
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }

    public function getUserAgents(Request $request)
    {
        $userAgents = UserAgent::where('user_id', $request->user->uuid)->get();
        $agentResource = AgentResource::allAgentDetails($userAgents);
        return $this->sendResponse('User agents', $agentResource);
    }

    public function detailUserAgent(Request $request, $userAgentId)
    {
        $userAgent = UserAgent::where('user_id', $request->user->uuid)
                              ->where('id', $userAgentId)
                              ->first();

        if (!$userAgent) {
            $this->sendError('User agent not found');
        }

        $agentResource = AgentResource::userAgentDetails($userAgent);

        return $this->sendResponse("User agent detail", $agentResource);
    }

    public function updateUserAgent(Request $request, $userAgentId)
    {
        $data = $request->all();
        $validate = Validator::make($data, ['password' => 'required|string']);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        $userAgent = UserAgent::where('id', $userAgentId)
                            ->where('user_id', $request->user->uuid)
                            ->where('type', 'integration')
                            ->first();
        if (!$userAgent) {
            return $this->sendError('User agent does not exist');
        }

        $agentDetail = AgentTokenService::getAgentToken($userAgent->email, $data['password']);
        if (!$agentDetail) {
            return $this->sendError('Agent token info', [
                "errorCode" => "100004",
                "errorMessage" => "Invalid agent credential"
            ], 401);
        }

        $userAgent->password = $data['password'];
        $userAgent->update($agentDetail);
        $userAgent->save();

        return $this->sendResponse('Password updated');
    }

    public function deleteUserAgent(Request $request, $userAgentId)
    {
        if (!$userAgentId) {
            return $this->sendError('User agent ID required');
        }
        $userAgent = UserAgent::where('id', $userAgentId)
                            ->where('user_id', $request->user->uuid)
                            ->where('type', 'integration')
                            ->first();
        if (!$userAgent) {
            return $this->sendError('User agent does not exist');
        }

        $userAgent->delete();
        return $this->sendResponse('User agent deleted');
    }

    public function getUserAgentToken(Request $request, $userAgentId)
    {
        $userAgent = UserAgent::where('user_id', $request->user->uuid)
                              ->where('id', $userAgentId)
                              ->first();

        if (!$userAgent) {
            $this->sendError('User agent not found');
        }

        if ($userAgent->type === 'profile') {
            $userAgent = Agent::where('id', $userAgent->agent_id)->first();
        }

        if (!$userAgent->access_token) {
            $agentToken = AgentTokenService::getAgentToken($userAgent->email, $userAgent->password);
            if (!$agentToken) {
                Logger::error('Agent credentials invalid for ' . $userAgent->email);
                return $this->sendError("Error occurred");
            }
            $userAgent->update(['access_token' => $agentToken['access_token']]);
        }

        // Get the current time
        $current_date_time = Carbon::now();
        $token_last_update = $userAgent->updated_at;

        // Check if the token last updated datetime is more than 21 hours ago
        if ($token_last_update->diffInHours($current_date_time) > 21) {
            $new_token = AgentTokenService::getAgentToken($userAgent->email, $userAgent->password);
            if (!$new_token) {
                return $this->sendError('Agent token info', [
                    "errorCode" => "100004",
                    "errorMessage" => "Invalid agent credential"
                ], 401);
            }
            $userAgent->update(["access_token" => $new_token['access_token']]);
        }

        return $this->sendResponse("User agent detail", ["access_token" => $userAgent->access_token]);
    }
}
