<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\OrderPosted;
use App\Logging\Logger;
use App\Services\CartItemService;

class CleanCartItems
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
    public function handle(OrderPosted $event): void
    {
        Logger::debug("Received event to cache created order");
        CartItemService::cleanCartItems(
            bookingReference: $event->bookingReference,
            cartItemIds: $event->cartItemIds,
            intent: $event->intent,
            requestData: $event->requestData,
            responseData: $event->responseData,
            userId: $event->userId,
            paymentGateway: $event->paymentGateway,
            userAgentId: $event->userAgentId,
            quoteId: $event->quoteId,
        );
        Logger::info("Cached orders of user {$event->userId}");
    }
}
