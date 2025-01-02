<?php

namespace App\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Services\IEmailService;
use App\Services\OtpService;
use App\Logging\Logger;

class SendProfileEmailOtp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        try {
            $user = User::findOrFail($this->userId);
            $otp = OtpService::generateOtp($user);
            if ($otp['success'] == false) {
                throw new Exception($otp['error']);
            }
            $email = $user->email;
            if ($user->sso_type === 'apple') {
                if (is_null($user->verified_email)) {
                    throw new Exception('Verified email not set to send otp');
                }
                $email = $user->verified_email;
            }
            $data = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'to' => [
                    [
                        'email' => $email
                    ]
                ],
                'subject' => 'Verify email',
                'htmlContent' => view('email.verifyEmailOTP', ['otp' => $otp['otpDetails']->otp])->render(),
            ];

            $response = $emailService->sendMail($data);

            Logger::info('Profile setup email verification sent to ' . $email);
        } catch (\Exception $e) {
            Logger::error('Failed to send email to user with id:' . $this->userId, $e);
            throw $e;
        }
    }
}
