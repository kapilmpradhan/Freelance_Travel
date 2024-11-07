<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\BrevoEmailService;

class SendForgotPasswordOtp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function handle(BrevoEmailService $brevoEmailService)
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

            $brevoEmailService->sendMail($data);
        } catch (\Exception $e) {
            Log::error('Failed to send email. Error: ' . $e->getMessage());
        }
    }
}