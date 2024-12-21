<?php

namespace App\Http\Controllers\Api;

use App\Logging\Logger;
use App\Models\AgentToken;
use App\Models\ProfileToken;
use Illuminate\Http\Request;
use App\Services\AgentTokenService;

class ProfileAgentController extends BaseController
{
    public function getProfileAgent(Request $request)
    {
        $user = $request->user;

        $profileAgent = ProfileToken::where('user_id', $user->uuid)
                                    ->first();

        $agent = AgentToken::where('username', $profileAgent->agent_email)->first();

        $agentToken = AgentTokenService::getAgentToken($agent->username, $agent->password);

        if (!$agentToken) {
            Logger::error('Invalid credentials to get profile token');
            return $this->sendError('Profile token info', [
                "errorCode" => "100004",
                "errorMessage" => "Invalid agent credential"
            ], 401);
        }
        $agent->update($agentToken);

        return $this->sendResponse('Profile token info', $agent->toArray());
    }
}
