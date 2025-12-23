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
        Logger::debug('Booking notification data event received', [
            'log_file' => config('logging.log_files.booking'),
            'user_order_id' => $event->userOrder->id,
            'action' => 'booking_notification_event_received',
        ]);

        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));
        $platform = $agentType->isCommissionAgent ? AgentBranchCode::DEFAULT : $event->platform;

        $addBookingDataResponse = BookingNotificationService::addBookingDataToNotification(
            cartItems: $event->cartItems,
            userOrder: $event->userOrder,
            platform: $platform
        );

        if ($addBookingDataResponse->success()) {
            Logger::debug('Booking notification data added', [
                'log_file' => config('logging.log_files.booking'),
                'user_id' => $userId,
                'user_order_id' => $event->userOrder->id,
                'action' => 'booking_notification_data_added',
            ]);
        }
    }
}
