<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Logging\Logger;
use App\Models\UserAgent;
use App\Models\Agent;
use App\Services\TdmsService;

class UserProfileAgentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $user;
    protected $new_email;

    /**
     * Create a new job instance.
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $email = $this->user->email;
            $customerLastOrderBranch = TdmsService::getCustomerLastOrderBranch($email);
            $defaultAgent = Agent::where('branch_code', config(key: 'vars.default_agent_branch_code'))->first();

            if ($customerLastOrderBranch) {
                $agent = Agent::where('branch_code', $customerLastOrderBranch)->first();
                if (!$agent) {
                    $agent = $defaultAgent;
                }
            } else {
                $agent = $defaultAgent;
            }

            $userAgent = UserAgent::create([
                "agent_id" => $agent->id,
                "user_id" => $this->user->uuid,
                "type" => 'profile'
            ]);

            $this->user->profile_status = 'success';
            $this->user->save();
            Logger::info('User profile setup complete for ' . $email);
        } catch (Exception $e) {
            Logger::error("User profile setup failed for " . $email, $e);
            throw $e;
        }
    }
}
