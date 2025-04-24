<?php

namespace App\Jobs;

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

    public function __construct($user, $otp)
    {
        $this->user = $user;
        $this->otp = $otp;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        try {
            // Prepare the data for sending via the Brevo API
            $data = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'to' => [
                    [
                        'email' => $this->user->email,
                    ]
                ],
                'templateId' => (int) config('vars.forgot_password_template_id'),
                'params' => [
                    'firstName' => $this->user->first_name,
                    'otp' => $this->otp,
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
