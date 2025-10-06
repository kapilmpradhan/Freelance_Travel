<?php

namespace App\DTOs;

use App\Models\UserAgent;
use App\Services\UserAgentService;

class UserAgentDTO
{
    public $agent;
    public $platform;
    public $isDefaultAgent;
    public $isPointsAgent;
    public $isCommissionAgent;

    public function __construct(
        $agent,
        $platform,
        bool $isDefaultAgent = false,
        bool $isPointsAgent = false,
        bool $isCommissionAgent = false
    ) {
        $this->agent = $agent;
        $this->platform = $platform;
        $this->isDefaultAgent = $isDefaultAgent;
        $this->isPointsAgent = $isPointsAgent;
        $this->isCommissionAgent = $isCommissionAgent;
    }

    public static function getUserAgent($user, $platform)
    {
        $isDefaultAgent = false;
        $isPointsAgent = false;
        $isCommissionAgent = false;

        $agent = UserAgent::where('user_id', $user->uuid)
            ->first();

        if ($agent) {
            $agent = UserAgentService::refreshAgent($agent);

            if ($agent->subsystem_type == 'FTA') {
                $isCommissionAgent = true;
            } else {
                $isPointsAgent = true;
            }
        } else {
            $platform = app('platform');
            $agentResponse = UserAgentService::getUserAgentByBranch($platform);
            $agent = $agentResponse->data;
            $isDefaultAgent = true;
        }

        return new self(
            $agent,
            $platform,
            $isDefaultAgent,
            $isPointsAgent,
            $isCommissionAgent
        );
    }
}
