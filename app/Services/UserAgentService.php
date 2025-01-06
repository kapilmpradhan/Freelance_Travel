<?php

namespace App\Services;

use App\Models\AgentToken;
use App\Models\UserAgent;
use App\Models\Agent;
use Carbon\Carbon;
use App\Services\TdmsService;

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

    public static function refreshAccessToken($agent)
    {
        if (
            !$agent->access_token
            || $agent->updated_at->diffInHours(Carbon::now()) > 21
        ) {
            $new_token = TdmsService::getAgentToken(
                username: $agent->email,
                password: $agent->password,
            );
            $agent->update(["access_token" => $new_token['access_token']]);
            $agent->refresh();
        }

        return $agent;
    }

    public static function getDefaultAgentToken()
    {
        $default_agent_token = Agent::where('email', config('vars.default_agent_email'))->first();
        if (!$default_agent_token) {
            $default_agent_token = Agent::create([
                "branch_code" => config('vars.default_agent_branch_code'),
                "email" => config('vars.default_agent_email'),
                "password" => config('vars.default_agent_password')
            ]);
        }

        if (
            !$default_agent_token->access_token
            || $default_agent_token->updated_at->diffInHours(Carbon::now()) > 21
        ) {
            $new_token = TdmsService::getAgentToken(
                username: $default_agent_token->email,
                password: $default_agent_token->password,
            );
            $default_agent_token->update(["access_token" => $new_token['access_token']]);
        }

        return $default_agent_token->access_token;
    }

    public static function getUserProfileAgent($userId)
    {
        $userAgent = UserAgent::where('user_id', $userId)
                              ->where('type', 'profile')
                              ->first();
        $agent = Agent::where('id', $userAgent->agent_id)->first();

        return UserAgentService::refreshAccessToken($agent);
    }
}
