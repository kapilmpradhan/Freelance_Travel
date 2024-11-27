<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Logging\Logger;
use App\Models\AgentToken;
use App\Services\AgentTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                return $this->sendError('Invalid credential');
            }
            $agentTokenDetail['user_id'] = $data['user_id'];
            $agentTokenDetail['username'] = $data['username'];
            $agentTokenDetail['password'] = $data['password'];

            $agentToken = $agentToken->create($agentTokenDetail);
            return $this->sendResponse('Agent token added', $agentToken->toArray(), 201);
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }

    public function getAgentToken(Request $request)
    {
        $user_id = $request->user->uuid;

        try {
            $agentToken = AgentToken::where('user_id', $user_id)->first();

            if (!$agentToken) {
                return $this->sendResponse('Agent token not added', null);
            }
            // Get the current time
            $current_date_time = Carbon::now();
            $token_last_update = $agentToken->updated_at;

            // Check if the token last updated datetime is more than 21 hours ago
            if ($token_last_update->diffInHours($current_date_time) > 21) {
                $new_token = AgentTokenService::getAgentToken($agentToken->username, $agentToken->password);
                if (!$new_token) {
                    $new_token = [];
                    $new_token['access_token'] = '';
                }
                $agentToken->update($new_token);
            }
            return $this->sendResponse('Agent token info', $agentToken->toArray());
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }

    public function getSharedToken(Request $request)
    {
        $agent_username = env('SHARED_TOKEN_AGENT_USERNAME');
        $agent_password = env('SHARED_TOKEN_AGENT_PASSWORD');

        $available_shared_token = AgentToken::where('type', 'shared')->first();

        if ($available_shared_token) {
            // Get the current time
            $current_date_time = Carbon::now();
            $token_last_update = $available_shared_token->updated_at;

            // Check if the token last updated datetime is more than 21 hours ago
            if ($token_last_update->diffInHours($current_date_time) > 21) {
                $new_token = AgentTokenService::getAgentToken($agent_username, $agent_password);
                if (!$new_token) {
                    Logger::error('Invalid credentials to get agent shared token');
                    return $this->sendError('Invalid credentials');
                }
                $available_shared_token->update($new_token);
            }
            return $this->sendResponse('Shared token', [
                'access_token' => $available_shared_token->access_token
            ]);
        }

        $agent_token_detail = AgentTokenService::getAgentToken($agent_username, $agent_password);
        if (isset($agent_token_detail['statusCode'])) {
            return $this->sendError('Invalid credentials');
        }
        $agent_token_detail['username'] = 'n/a';
        $agent_token_detail['password'] = 'n/a';
        $agent_token_detail['type'] = 'shared';

        $new_token = AgentToken::create($agent_token_detail);

        return $this->sendResponse('Shared token', [
            'access_token' => $new_token->access_token
        ]);
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
            return $this->sendError('Agent token not added');
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
            return $this->sendError('Error occured');
        }
    }

    public function removeAgentToken(Request $request)
    {
        $user_id = $request->user->uuid;

        try {
            $agentToken = AgentToken::where('user_id', $user_id)->first();
            if (!$agentToken) {
                return $this->sendError('Agent token not added');
            }
            $agentToken->delete();
            return $this->sendResponse('Agent token removed');
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }
}
