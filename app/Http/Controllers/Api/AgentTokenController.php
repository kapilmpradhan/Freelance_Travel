<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentToken;
use Exception;
use Illuminate\Http\Request;
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
            $agentToken = $agentToken->create($data);
            return $this->sendResponse($agentToken->toArray(), 'successfully', 200);
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
            return $this->sendResponse($agentToken->toArray(), 'successfully', 200);
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
            return $this->sendResponse([], 'successfully', 200);
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }
}
