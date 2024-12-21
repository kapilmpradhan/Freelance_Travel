<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Logging\Logger;
use App\Models\AgentToken;
use App\Models\ProfileToken;
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

            $agent = AgentToken::where('type', 'default')->first();

            $customerLastOrder = TdmsService::getCustomerLastOrder($agent, $email);
            if ($customerLastOrder) {
                $agentEmail = $customerLastOrder['salesAgentEmail'];
                $branchCode = $customerLastOrder['salesBranchCode'];
            } else {
                $profileToken = ProfileToken::where('agent_email', $agent->username)->first();
                if ($profileToken) {
                    $agentEmail = $profileToken->agent_email;
                    $branchCode = $profileToken->branch_code;
                } else {
                    $agentEmail = $agent->username;
                    $branchCode = "TST"; # TODO: Modify this after customer API is avaialble.
                }
            }

            ProfileToken::create([
                    "agent_email" => $agentEmail,
                    "user_id" => $this->user->uuid,
                    "branch_code" => $branchCode,
                ]);

            $this->user->profile_status = 'success';
            $this->user->save();
            Logger::info('User profile setup complete for ' . $email);
        } catch (Exception $e) {
            Logger::error("User profile setup failed for " . $email, $e);
        }
    }
}
