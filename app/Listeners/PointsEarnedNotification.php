<?php

namespace App\Listeners;

use App\DTOs\UserAgentDTO;
use App\Events\CompleteOrderEvent;
use App\Logging\Logger;
use App\Jobs\SendPointsEarnedNotificationJob;
use App\Models\User;

class PointsEarnedNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(CompleteOrderEvent $event): void
    {
        Logger::debug('Points earned notification event received', [
            'log_file' => config('logging.log_files.notifications'),
            'user_order_id' => $event->userOrder->id,
            'action' => 'points_earned_event_received',
        ]);

        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));

        SendPointsEarnedNotificationJob::dispatch($event->userOrder, app('platform'), $agentType);

        Logger::debug('Points earned notification job dispatched', [
            'log_file' => config('logging.log_files.notifications'),
            'user_id' => $userId,
            'user_order_id' => $event->userOrder->id,
            'action' => 'points_earned_notification_dispatched',
        ]);
    }
}
