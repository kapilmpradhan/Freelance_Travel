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

class NotificationToInactiveUsers implements ShouldQueue
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
        Logger::info('NotificationToInActiveUsers job started');

        $availableDiscount = DiscountService::getActiveDiscount();
        if ($availableDiscount->isError()) {
            Logger::error('No active discount found');
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
            Logger::info('No inactive users found');
            return;
        } else {
            Logger::info('Inactive users found. Sending notification');
            $fcmService = new FcmService();

            $subscribeResponse = $fcmService->subscribeTokensToTopic('inactive_users', $fcmTokens);
            if ($subscribeResponse->isError()) {
                Logger::error('Error subscribing inactive users to topic');
            }

            $discountData = $availableDiscount->data;

            $notification = [
                'title' => $discountData['title'],
                'body' => $discountData['description']
            ];

            $fcmService->sendNotification(
                notification: $notification,
                topic: 'inactive_users',
            );

            Logger::info('Notification sent to inactive users');

            Logger::info('Unsubscribing inactive users from topic');
            $fcmService->unsubscribeTokensFromTopic('inactive_users', $fcmTokens);
            return;
        }
    }
}
