<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;

class SendAccountDeletionEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $user;
    protected $platform;

    public function __construct($user, $platform)
    {
        $this->user = $user;
        $this->platform = $platform;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        if ($this->platform == AgentBranchCode::DEFAULT) {
            $mailFromName = config('vars.mail_from_name');
            $mailFromAddress = config('vars.mail_from_address');
            $supportEmail = config('vars.notification_to_freelance_email_address');
            $templateId = config('vars.account_deletion_template_id');
        } elseif ($this->platform == AgentBranchCode::PETERPANS) {
            $mailFromName = config('vars.peterpans_mail_from_name');
            $mailFromAddress = config('vars.peterpans_mail_from_address');
            $supportEmail = config('vars.notification_to_freelance_email_address');
            $templateId = config('vars.peterpans_account_deletion_template_id');
        }

        $emailData = [
            'sender' => [
                'name' => $mailFromName,
                'email' => $mailFromAddress
            ],
            'to' => [
                    ['email' => $this->user->email],
                    ['email' => $supportEmail]
                ],
            'templateId' => (int) $templateId,
            'params' => [
                'firstName' => $this->user->first_name ?? 'user',
                'email' => $this->user->email,
                'supportEmail' => $supportEmail,
                'platform' => $mailFromName
            ]
        ];

        $emailService->sendMail($emailData);

        Logger::info("Account deletion email sent to {$this->user->email}");
    }
}
