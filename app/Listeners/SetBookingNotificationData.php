<?php

namespace App\Listeners;

use App\DTOs\UserAgentDTO;
use App\Enums\AgentBranchCode;
use App\Events\CompleteOrderEvent;
use App\Logging\Logger;
use App\Models\User;
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

        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));
        $platform = $agentType->isCommissionAgent ? AgentBranchCode::DEFAULT : $event->platform;

        $addBookingDataResponse = BookingNotificationService::addBookingDataToNotification(
            cartItems: $event->cartItems,
            userOrder: $event->userOrder,
            platform: $platform
        );

        if ($addBookingDataResponse->success()) {
            Logger::info('Booking data added for notification');
        }
    }
}
