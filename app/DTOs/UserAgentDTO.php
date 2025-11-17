<?php

namespace App\DTOs;

use App\Enums\AgentBranchCode;
use App\Services\UserAgentService;
use Exception;

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

        if (is_null($user)) {
            $agentResponse = UserAgentService::getDefaultAgent();
        } else {
            $agentResponse = UserAgentService::getUserAgentIfExistsElseDefault($user ? $user->uuid : null);
        }

        if ($agentResponse->isError()) {
            throw new Exception('Unable to get agent');
        }

        $agent = $agentResponse->data;
        if (in_array($agent->branch_code, [AgentBranchCode::PETERPANS, AgentBranchCode::DEFAULT])) {
            $isDefaultAgent = true;
        } elseif ($agent->subsystem_type == 'FTA') {
            $isCommissionAgent = true;
        } else {
            $isPointsAgent = true;
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
