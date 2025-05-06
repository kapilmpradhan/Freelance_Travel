<?php

namespace App\Jobs;

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

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        $emailData = [
            'sender' => [
                'email' => config('vars.mail_from_address')
            ],
            'to' => [
                    ['email' => $this->user->email],
                    ['email' => config('vars.notification_to_freelance_email_address')]
                ],
            'templateId' => (int) config('vars.account_deletion_template_id'),
            'params' => [
                'firstName' => $this->user->first_name ?? 'user',
                'email' => $this->user->email,
                'supportEmail' => config('vars.mail_from_address')
            ]
        ];

        $emailService->sendMail($emailData);

        Logger::info("Account deletion email sent to {$this->user->email}");
    }
}
