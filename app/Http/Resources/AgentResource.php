<?php

namespace App\Http\Resources;

class AgentResource
{
    public static function agentOverview($agent)
    {
        return [
            'id' => $agent->id,
            'email' => $agent->email,
            'type' => $agent->type
        ];
    }

    public static function allAgentDetails($agents)
    {
        $all = [];
        foreach ($agents as $agent) {
            $all[] = AgentResource::agentOverview($agent);
        }

        return $all;
    }

    public static function userAgentDetails($agent)
    {
        $agent = $agent->toArray();
        unset($agent['access_token'], $agent['is_active'], $agent['is_deleted']);

        return $agent;
    }
}
