<?php

namespace App\Http\Controllers\Api;

use Validator;
use App\Models\AgentToken;
use Exception;
use Illuminate\Http\Request;

use function PHPUnit\Framework\returnSelf;

class AgentTokenController extends BaseController
{
    public function saveAgentToken(Request $request, AgentToken $agentToken)
    {
        $validate = Validator::make($request->all(), $agentToken->rule());
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        try {
            $create = $agentToken->storeAgentToken(($request));
            return $this->sendResponse('Saved', 'successfullt', 200);
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }
}