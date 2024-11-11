<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\IEmailService;

class SendForgotPasswordOtp implements ShouldQueue
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
            // Prepare the data for sending via the Brevo API
            $data = [
                'sender' => [
                    'email' => env('MAIL_FROM_ADDRESS')
                ],
                'to' => [
                    [
                        'email' => $this->email
                    ]
                ],
                'subject' => 'Forgot password OTP',
                'htmlContent' => view('email.forgotPasswordOTP', ['otp' => $this->otp])->render(),
            ];

            $response = $emailService->sendMail($data);

            echo $response;
        } catch (\Exception $e) {
            echo 'Failed to send email. Error: ' . $e->getMessage();
        }
    }
}
