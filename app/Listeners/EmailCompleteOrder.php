<?php

namespace App\Listeners;

use App\DTOs\UserAgentDTO;
use App\Events\CompleteOrderEvent;
use App\Jobs\SendOrderCompleteEmail;
use App\Logging\Logger;
use App\Models\User;
use App\Models\UserOrder;

class EmailCompleteOrder
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
        Logger::debug('Order complete event received', [
            'log_file' => config('logging.log_files.order'),
            'user_order_id' => $event->userOrder->id,
            'action' => 'order_complete_event_received',
        ]);

        $userOrderId = $event->userOrder->id;
        $userOrder = UserOrder::where('id', $userOrderId)->first()->request_data;
        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));

        SendOrderCompleteEmail::dispatch($userOrder, app('platform'), $agentType);

        Logger::debug('Order complete email job dispatched', [
            'log_file' => config('logging.log_files.order'),
            'user_id' => $userId,
            'user_order_id' => $userOrderId,
            'action' => 'order_complete_email_dispatched',
        ]);
    }
}
