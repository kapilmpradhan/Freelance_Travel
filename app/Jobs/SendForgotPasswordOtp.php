<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use App\Services\BrevoEmailService;
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
            // Prepare the data for sending via the Brevo API
            $sender = BrevoEmailService::platformSenderDetail($this->platform);
            $supportEmail = $this->platform == AgentBranchCode::PETERPANS
                ? config('vars.notification_to_peterpans_email_address')
                : config('vars.notification_to_freelance_email_address');

            $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
                ? 'peterpans_forgot_password_template_id'
                : 'forgot_password_template_id';

            $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
                ? 'peterpans_forgot_password_template_id'
                : 'forgot_password_template_id';

            $data = [
                'sender' => $sender,
                'to' => [
                    [
                        'email' => $this->user->email,
                    ]
                ],
                'templateId' => (int) config('vars.' . $templateIdVarName),
                'params' => [
                    'firstName' => $this->user->first_name,
                    'otp' => $this->otp,
                    'platform' => $sender['name'],
                    'supportEmail' => $supportEmail,
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
