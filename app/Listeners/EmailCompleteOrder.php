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
        $className = get_class($this);
        Logger::debug("[{$className}] Received order created event");

        $userOrderId = $event->userOrder->id;
        $userOrder = UserOrder::where('id', $userOrderId)->first()->request_data;
        $userId = $event->userOrder->user_id;
        $agentType = UserAgentDTO::getUserAgent(User::find($userId), app('platform'));

        SendOrderCompleteEmail::dispatch($userOrder, app('platform'), $agentType);

        Logger::debug("[{$className}] Dispatched job to send complete order mail");
    }
}
