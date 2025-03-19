<?php

namespace App\Listeners;

use App\Events\CompleteOrderEvent;
use App\Logging\Logger;
use App\Services\BookingNotificationService;

class SetBookingNotificationData
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event
     */
    public function handle(CompleteOrderEvent $event)
    {
        Logger::debug("Received event to set booking notification data");

        $addBookingDataResponse = BookingNotificationService::addBookingDataToNotification(
            cartItems: $event->cartItems,
            userOrder: $event->userOrder
        );

        if ($addBookingDataResponse->success()) {
            Logger::info('Booking data added for notification');
        }
    }
}
