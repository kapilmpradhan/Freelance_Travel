<?php

namespace App\Services;

use App\Models\AgentToken;

class UserAgentService
{
    public static function retrieveAgentData(string $username, string $password)
    {
        #TODO: get credential from provided agent data. Remove agent_tokens table later
        $agentToken = AgentToken::where("username", $username)
                                ->where("password", $password)
                                ->first();

        if ($agentToken) {
            return [
                "email" => $agentToken->username,
                "branch_code" => "TST" # TODO: Using 'TST' now, get it from provided data later.
            ];
        } else {
            return null;
        }
    }
}
