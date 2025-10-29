<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Services\OtpService;
use App\Logging\Logger;
use App\Services\BrevoEmailService;
use Throwable;

class SendProfileEmailOtp implements ShouldQueue
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
        try {
            $user = $this->user;
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

            $sendMailResponse = $emailService->sendMailV2($data);

            $logData = [
                'log_file' => config('logging.log_files.account_verification'),
                'user_id' => $user->uuid,
                'user_email' => $email,
                'platform' => $this->platform,
                'response' => json_encode($sendMailResponse->data)
            ];

            if ($sendMailResponse->isSuccess()) {
                Logger::info(
                    message: 'Account verification email sent successfully.',
                    data: $logData,
                    write: true
                );
            } else {
                Logger::error(
                    message: 'Failed to send account verification email.',
                    data: $logData,
                    write: true
                );
            }
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Failed to send account verification email.',
                data: $logData ?? [],
                exception: $e,
                write: true
            );
            throw $e;
        }
    }
}
