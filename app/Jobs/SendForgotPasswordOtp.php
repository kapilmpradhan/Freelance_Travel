<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;

class SendForgotPasswordOtp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $user;
    protected $otp;
    protected $platform;

    public function __construct($user, $otp, $platform)
    {
        $this->user = $user;
        $this->otp = $otp;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        try {
            $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
                ? 'peterpans_forgot_password_template_id'
                : 'forgot_password_template_id';

            $data = [
                'to' => [
                    [
                        'email' => $this->user->email,
                    ]
                ],
                'templateId' => (int) config('vars.' . $templateIdVarName),
                'params' => [
                    'firstName' => $this->user->first_name,
                    'otp' => $this->otp
                ],
            ];

            $response = $emailService->sendMail($data);

            echo $response;
        } catch (\Exception $e) {
            echo 'Failed to send email. Error: ' . $e->getMessage();
            throw $e;
        }
    }
}
