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
}