<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\User;
use App\Services\BrevoEmailService;
use App\Services\FcmService;
use App\Services\IEmailService;
use App\Services\TdmsService;
use App\Services\UserAgentService;
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

    public function __construct($data, $platform)
    {
        $this->data = $data;
        $this->platform = $platform;
    }

    // Prepare mobile notification data
    public function getFcmNotificationData($user, $points)
    {
        $tokens = $user->fcmTokens();

        $data = ['path' => "/pointHistory"];

        $notification = [
            'title' => 'Points Earned',
            'body' => "You just earned {$points} points."
        ];

        $fcmNotificationData = [
            "notification" => $notification,
            "data" => $data
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

        $agentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);
        $agentToken = $agentResponse->data->access_token;

        $getCommissionReportResponse = TdmsService::getCommissionReport(
            bookingReference: $bookingReference,
            agentToken: $agentToken
        );

        if (!$getCommissionReportResponse) {
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
            Logger::error("No commission report found for booking reference: {$bookingReference}");
            return;
        }

        // Email notification data
        $sender = BrevoEmailService::platformSenderDetail($this->platform);
        $customerData = [
            'sender' => $sender,
            'to' => [
                [
                    'email' => $user->email
                ]
            ],
            'templateId' => (int) config('vars.points_earned_template_id'),
            'params' => [
                'bookingReference' => $bookingReference,
                'earnedPoints' => $requiredReport['amount'],
                'availableDate' => $requiredReport['availableDate'],
                'platform' => $sender['name'],
            ]
        ];

        // Mobile notification data
        $fcmNotificationData = $this->getFcmNotificationData($user, $requiredReport['amount']);

        // Send mobile notification
        $fcmService->sendNotification(
            token: $fcmNotificationData['token'],
            notification: $fcmNotificationData['notification'],
            data: $fcmNotificationData['data'],
            topic: $fcmNotificationData['topic']
        );

        // Send email notification
        $emailService->sendMail($customerData);
    }
}
