<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use App\Models\AgentToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\AgentTokenService;

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
            $agentToken = $agentToken->create($data);
            return $this->sendResponse('Agent token added', $agentToken->toArray());
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
                return $this->sendError('Agent token not added');
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
