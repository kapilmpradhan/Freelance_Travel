<?php

namespace App\Listeners;

use App\Events\CompleteOrderEvent;
use App\Logging\Logger;
use App\Jobs\SendPointsEarnedNotificationJob;

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

        SendPointsEarnedNotificationJob::dispatch($event->userOrder);

        Logger::debug("[{$className}] Dispatched job to send complete order mail");
    }
}
