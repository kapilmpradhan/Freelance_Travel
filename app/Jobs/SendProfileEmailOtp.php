<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
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
use App\Services\BrevoEmailService;

class SendProfileEmailOtp implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $userId;
    protected $platform;

    public function __construct($userId, $platform)
    {
        $this->userId = $userId;
        $this->platform = $platform;
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
                Logger::info($otp['error']);
                return;
            }
            $email = $user->email;
            if ($user->sso_type === 'apple') {
                if (is_null($user->verified_email)) {
                    throw new Exception('Verified email not set to send otp');
                }
                $email = $user->verified_email;
            }

            $sender = BrevoEmailService::platformSenderDetail($this->platform);

            $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
                ? 'peterpans_verify_email_template_id'
                : 'verify_email_template_id';

            $data = [
                'sender' => $sender,
                'to' => [
                    [
                        'email' => $email
                    ]
                ],
                'templateId' => (int) config('vars.' . $templateIdVarName),
                'params' => [
                    'firstName' => $user->first_name,
                    'otp' => $otp['otpDetails']->otp,
                    'year' => date('Y'),
                    'platform' => $sender['name']
                ]
            ];

            $response = $emailService->sendMail($data);
            $response = json_decode($response, true);

            Logger::info('Profile setup email verification sent to ' . $email);
        } catch (Exception $e) {
            Logger::error('Failed to send email to user with id:' . $this->userId, $e);
            throw $e;
        }
    }
}
