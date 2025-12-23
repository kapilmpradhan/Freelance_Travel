<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use App\Models\User;
use App\Services\BrevoEmailService;
use App\Services\FcmService;
use App\Services\IEmailService;
use App\Services\TdmsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPointsEarnedNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $data;
    protected $platform;
    protected $agentType;

    public function __construct($data, $platform, $agentType)
    {
        $this->data = $data;
        $this->platform = $platform;
        $this->agentType = $agentType;
    }

    // Prepare mobile notification data
    public function getFcmNotificationData($user, $points)
    {
        $tokens = $user->fcmTokens($this->platform);
        if (empty($tokens)) {
            return null;
        }

        $data = [
            'title' => 'Points You Will Earn',
            'body' => "You will earn {$points} points from last order.",
            'path' => "/pointDetail"
        ];

        $fcmNotificationData = [
            "data" => $data,
        ];

        if (count($tokens) > 1) {
            $fcmService = new FcmService();
            $fcmService->subscribeTokensToTopic(
                topic: $user->uuid,
                tokens: $tokens
            );

            $fcmNotificationData['token'] = null;
            $fcmNotificationData['topic'] = $user->uuid;
        } else {
            $fcmNotificationData['token'] = $tokens[0] ?? null;
            $fcmNotificationData['topic'] = null;
        }

        return $fcmNotificationData;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService, FcmService $fcmService)
    {
        $bookingReference = $this->data->booking_reference;
        $userId = $this->data->user_id;
        $user = User::where('uuid', $userId)->first();

        Logger::debug('Processing points earned notification', [
            'log_file' => config('logging.log_files.notifications'),
            'user_id' => $userId,
            'booking_reference' => $bookingReference,
            'action' => 'points_earned_notification_start',
        ]);

        $agentToken = $this->agentType->agent->access_token;

        $getCommissionReportResponse = TdmsService::getCommissionReport(
            agentToken: $agentToken,
            startDate: Carbon::now()->subdays(2),
            endDate: Carbon::now(),
            onlyAvailablePoints: false
        );

        if (!$getCommissionReportResponse) {
            Logger::debug('No commission report response', [
                'log_file' => config('logging.log_files.notifications'),
                'booking_reference' => $bookingReference,
                'action' => 'points_earned_no_response',
            ]);
            return;
        }

        $commissionData = $getCommissionReportResponse->data['cashback'];
        $requiredReport = [];
        foreach ($commissionData as $commission) {
            if ($commission['bookingReference'] === $bookingReference) {
                $requiredReport = $commission;
                break;
            }
        }

        if (empty($requiredReport)) {
            Logger::error("No commission report found for booking reference: {$bookingReference}", data: [
                'log_file' => config('logging.log_files.notifications'),
                'booking_reference' => $bookingReference,
                'action' => 'points_earned_no_report',
            ]);
            return;
        }

        $balance = (float) $requiredReport['balance'];

        if ($balance <= 0) {
            return;
        }

        // Email notification data
        $sender = BrevoEmailService::platformSenderDetail($this->platform);

        $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS && !$this->agentType->isCommissionAgent
            ? 'peterpans_points_earned_template_id'
            : 'points_earned_template_id';

        $customerData = [
            'to' => [
                [
                    'email' => $user->email
                ]
            ],
            'templateId' => (int) config('vars.' . $templateIdVarName),
            'params' => [
                'bookingReference' => $bookingReference,
                'earnedPoints' => $balance,
                'availableDate' => $requiredReport['availableDate'],
                'platform' => $sender['name'],
                'isCommissionAgent' => $this->agentType->isCommissionAgent
            ]
        ];

        // Mobile notification data
        $fcmNotificationData = $this->getFcmNotificationData($user, $balance);
        if (!is_null($fcmNotificationData)) {
            // Send mobile notification
            $fcmService->sendNotification(
                token: $fcmNotificationData['token'],
                data: $fcmNotificationData['data'],
                topic: $fcmNotificationData['topic']
            );
        }

        // Send email notification
        $emailService->sendMail($customerData);

        Logger::debug('Points earned notification sent', [
            'log_file' => config('logging.log_files.notifications'),
            'user_id' => $userId,
            'booking_reference' => $bookingReference,
            'points' => $balance,
            'action' => 'points_earned_notification_sent',
        ]);
    }
}
