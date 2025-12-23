<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\FirebaseFcmToken;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationToInactiveUsers implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::debug('Notification to inactive users job started', [
            'log_file' => config('logging.log_files.notifications'),
            'action' => 'inactive_users_notification_start',
        ]);

        $availableDiscount = DiscountService::getActiveDiscount();
        if ($availableDiscount->isError()) {
            Logger::debug('No active discount available', [
                'log_file' => config('logging.log_files.notifications'),
                'action' => 'inactive_users_no_discount',
            ]);
            return;
        }

        $inactiveUserIds = User::all()->filter(function ($user) {
            $daysDifference = Carbon::today()->diffInDays(Carbon::parse($user->last_login));
            if ($daysDifference <= 0) {
                return false;
            }
            return $daysDifference % 7 === 0;
        })->pluck('uuid')->toArray();

        $fcmTokens = FirebaseFcmToken::whereIn('user_id', $inactiveUserIds)
                                    ->pluck('token')
                                    ->toArray();
        if (count($fcmTokens) < 1) {
            Logger::debug('No inactive users found', [
                'log_file' => config('logging.log_files.notifications'),
                'action' => 'inactive_users_none_found',
            ]);
            return;
        } else {
            Logger::debug('Inactive users found, sending notification', [
                'log_file' => config('logging.log_files.notifications'),
                'users_count' => count($inactiveUserIds),
                'tokens_count' => count($fcmTokens),
                'action' => 'inactive_users_sending',
            ]);
            $fcmService = new FcmService();

            $subscribeResponse = $fcmService->subscribeTokensToTopic('inactive_users', $fcmTokens);
            if ($subscribeResponse->isError()) {
                Logger::error('Error subscribing inactive users to topic', data: [
                    'log_file' => config('logging.log_files.notifications'),
                    'action' => 'inactive_users_subscribe_error',
                ]);
            }

            $discountData = $availableDiscount->data;

            $data = [
                'title' => $discountData['title'],
                'body' => $discountData['description']
            ];

            $fcmService->sendNotification(
                data: $data,
                topic: 'inactive_users',
            );

            Logger::debug('Notification sent to inactive users', [
                'log_file' => config('logging.log_files.notifications'),
                'users_count' => count($inactiveUserIds),
                'action' => 'inactive_users_notification_sent',
            ]);

            $fcmService->unsubscribeTokensFromTopic('inactive_users', $fcmTokens);
            return;
        }
    }
}
