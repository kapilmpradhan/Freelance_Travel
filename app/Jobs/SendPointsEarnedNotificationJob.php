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
    protected $agent;

    public function __construct($data, $platform, $agent)
    {
        $this->data = $data;
        $this->platform = $platform;
        $this->agent = $agent;
    }

    // Prepare mobile notification data
    public function getFcmNotificationData($user, $points)
    {
        $tokens = $user->fcmTokens($this->platform);
        if (empty($tokens)) {
            return null;
        }

        $data = ['path' => "/pointDetail"];

        $notification = [
            'title' => 'Points You Will Earn',
            'body' => "You will earn {$points} points from last order."
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

        $agentToken = $this->agent->access_token;

        $getCommissionReportResponse = TdmsService::getCommissionReport(
            agentToken: $agentToken,
            startDate: Carbon::now()->subdays(2),
            endDate: Carbon::now(),
            onlyAvailablePoints: false
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

        $balance = (float) $requiredReport['balance'];

        if ($balance <= 0) {
            return;
        }

        // Email notification data
        $sender = BrevoEmailService::platformSenderDetail($this->platform);

        $templateIdVarName = $this->platform == AgentBranchCode::PETERPANS
            ? 'peterpans_points_earned_template_id'
            : 'points_earned_template_id';

        $customerData = [
            'sender' => $sender,
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
            ]
        ];

        // Mobile notification data
        $fcmNotificationData = $this->getFcmNotificationData($user, $balance);
        if (!is_null($fcmNotificationData)) {
            // Send mobile notification
            $fcmService->sendNotification(
                token: $fcmNotificationData['token'],
                notification: $fcmNotificationData['notification'],
                data: $fcmNotificationData['data'],
                topic: $fcmNotificationData['topic']
            );
        }

        // Send email notification
        $emailService->sendMail($customerData);
    }
}
