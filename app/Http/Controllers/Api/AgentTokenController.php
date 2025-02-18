<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Logging\Logger;
use App\Models\Agent;
use App\Models\AgentToken;
use Illuminate\Http\Request;
use App\Services\AgentTokenService;
use Illuminate\Support\Facades\Validator;

class AgentTokenController extends BaseController
{
    public function addAgentToken(Request $request, AgentToken $agentToken)
    {
        $data = $request->all();
        $data['user_id'] = $request->user->uuid;
        $validate = Validator::make($data, $agentToken->addAgentTokenRule());
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        try {
            $availableToken = AgentToken::where('user_id', $data['user_id'])->first();

            if ($availableToken) {
                return $this->sendError('Agent token already exists', []);
            }

            $agentTokenDetail = AgentTokenService::getAgentToken($data['username'], $data['password']);
            if (!$agentTokenDetail) {
                return $this->sendError('Agent token info', [
                    "errorCode" => "100004",
                    "errorMessage" => "Invalid agent credential"
                ]);
            }
            $agentTokenDetail['user_id'] = $data['user_id'];
            $agentTokenDetail['username'] = $data['username'];
            $agentTokenDetail['password'] = $data['password'];

            $agentToken = $agentToken->create($agentTokenDetail);
            return $this->sendResponse('Agent token added', $agentToken->toArray(), 201);
        } catch (Exception $e) {
            $errorMessage = "Failed to add agent token";
            Logger::error($errorMessage, $e);
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }

    public function getAgentToken(Request $request)
    {
        $user_id = $request->user->uuid;

        try {
            $agentToken = AgentToken::where('user_id', $user_id)->first();
            if (!$agentToken) {
                return $this->sendError('Agent token info', [
                    "errorCode" => "100003",
                    "errorMessage" => "Agent token not integrated"
                ]);
            }

            // Get the current time
            $current_date_time = Carbon::now();
            $token_last_update = $agentToken->updated_at;

            // Check if the token last updated datetime is more than 21 hours ago
            if ($token_last_update->diffInHours($current_date_time) > 21) {
                $new_token = AgentTokenService::getAgentToken($agentToken->username, $agentToken->password);
                if (!$new_token) {
                    return $this->sendError('Agent token info', [
                        "errorCode" => "100004",
                        "errorMessage" => "Invalid agent credential"
                    ], 401);
                }
                $agentToken->update($new_token);
            }
            return $this->sendResponse('Agent token info', $agentToken->toArray());
        } catch (Exception $e) {
            $errorMessage = "Failed to get agent token";
            Logger::error($errorMessage, $e);
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }

    public function getDefaultToken(Request $request)
    {
        $agent_username = config('vars.default_agent_email');
        $agent_password = config('vars.default_agent_password');

        $default_token = Agent::where('email', $agent_username)->first();
        if (!$default_token) {
            if (!$default_token) {
                Logger::error('Default agent credentials invalid for ' . $default_token->email);
                return $this->sendError("Internal server error");
            }
        }

        // Get the current time
        $current_date_time = Carbon::now();
        $token_last_update = $default_token->updated_at;

        // Check if the token last updated datetime is more than 21 hours ago
        if ($token_last_update->diffInHours($current_date_time) > 21) {
            $defaultAgentToken = AgentTokenService::getAgentToken($agent_username, $agent_password);
            $default_token->update(['access_token' => $defaultAgentToken['access_token']]);
        }
        return $this->sendResponse('Default token', ['access_token' => $default_token->access_token]);
    }

    public function updateAgentToken(Request $request, AgentToken $agentToken)
    {
        $data = $request->all();
        $user_id = $request->user->uuid;

        $validate = Validator::make($data, $agentToken->updateAgentTokenRule());
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors());
        }

        $availableToken = AgentToken::where('user_id', $user_id)->first();
        if (!$availableToken) {
            return $this->sendError('Agent token info', [
                "errorCode" => "100003",
                "errorMessage" => "Agent token not integrated"
            ]);
        }

        try {
            $updatedTokenDetaill = AgentTokenService::getAgentToken($data['username'], $data['password']);
            if ($updatedTokenDetaill) {
                $updatedTokenDetaill['username'] = $data['username'];
                $updatedTokenDetaill['password'] = $data['password'];
            }

            $availableToken->update($updatedTokenDetaill);
            return $this->sendResponse('Agent token updated', $availableToken->toArray());
        } catch (Exception $e) {
            $errorMessage = "Failed to update agent token";
            Logger::error($errorMessage, $e);
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }

    public function removeAgentToken(Request $request)
    {
        $user_id = $request->user->uuid;

        try {
            $agentToken = AgentToken::where('user_id', $user_id)->first();
            if (!$agentToken) {
                return $this->sendError('Agent token info', [
                    "errorCode" => "100003",
                    "errorMessage" => "Agent token not integrated"
                ]);
            }
            $agentToken->delete();
            return $this->sendResponse('Agent token removed');
        } catch (Exception $e) {
            $errorMessage = "Failed to remove agent token";
            Logger::error($errorMessage, $e);
            return $this->sendError('Error occured', [
                "errorCode" => "100005",
                "errorMessage" => $e->getMessage()
            ]);
        }
    }
}
