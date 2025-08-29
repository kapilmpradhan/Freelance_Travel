<?php

namespace App\Listeners;

use App\Events\CompleteOrderEvent;
use App\Logging\Logger;
use App\Jobs\SendPointsEarnedNotificationJob;
use App\Services\UserAgentService;

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
        Logger::debug("[{$className}] Received order created event");

        $agent = UserAgentService::getUserAgent($event->userOrder->user_id);
        if (!$agent->isSuccess()) {
            Logger::debug("[{$className}] User is not an agent. Skipping sending points earned notification.");
            return;
        }

        SendPointsEarnedNotificationJob::dispatch($event->userOrder, app('platform'), $agent->data);

        Logger::debug("[{$className}] Dispatched job to send complete order mail");
    }
}
