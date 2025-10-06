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
        $className = get_class($this);
        Logger::debug("[{$className}] Received points earned notification event");

        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));

        SendPointsEarnedNotificationJob::dispatch($event->userOrder, app('platform'), $agentType);

        Logger::debug("[{$className}] Dispatched job to send complete order mail");
    }
}
