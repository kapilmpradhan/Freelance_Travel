<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;

class SendProfileEmailOtp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $email;
    protected $otp;

    public function __construct($email, $otp)
    {
        $this->email = $email;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        try {
            $data = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'to' => [
                    [
                        'email' => $this->email
                    ]
                ],
                'subject' => 'Verify email',
                'htmlContent' => view('email.verifyEmailOTP', ['otp' => $this->otp])->render(),
            ];

            $response = $emailService->sendMail($data);

            Logger::info('Profile setup email verification sent to ' . $this->email);
        } catch (\Exception $e) {
            Logger::error('Failed to send email to ' . $this->email, $e);
            throw $e;
        }
    }
}
