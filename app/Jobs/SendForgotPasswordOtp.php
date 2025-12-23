<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use Throwable;

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
            $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
                ? 'peterpans_forgot_password_template_id'
                : 'forgot_password_template_id';

            $data = [
                'to' => [
                    [
                        'email' => $this->user->email,
                    ]
                ],
                'templateId' => (int) config('vars.' . $templateIdVarName),
                'params' => [
                    'firstName' => $this->user->first_name,
                    'otp' => $this->otp
                ],
            ];

            $sendMailResponse = $emailService->sendMailV2($data);

            $logData = [
                'log_file' => config('logging.log_files.forgot_password'),
                'user_id' => $this->user->uuid,
                'user_email' => $this->user->email,
                'platform' => $this->platform,
                'response' => json_encode($sendMailResponse->data)
            ];

            if ($sendMailResponse->isSuccess()) {
                Logger::debug(
                    'Forgot password OTP email sent successfully.',
                    $logData
                );
            } else {
                Logger::error(
                    message: 'Failed to send forgot password OTP email.',
                    data: $logData
                );
            }
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Failed to send forgot password OTP email.',
                data: $logData ?? [],
                exception: $e
            );
            throw $e;
        }
    }
}
