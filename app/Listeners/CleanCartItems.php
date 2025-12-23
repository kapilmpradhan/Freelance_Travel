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
        Logger::debug('Order posted event received - cleaning cart', [
            'log_file' => config('logging.log_files.cart'),
            'user_id' => $event->userId,
            'booking_reference' => $event->bookingReference,
            'action' => 'clean_cart_event_received',
        ]);

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
            sessionId: $event->sessionId
        );

        Logger::debug('Cart items cleaned after order', [
            'log_file' => config('logging.log_files.cart'),
            'user_id' => $event->userId,
            'booking_reference' => $event->bookingReference,
            'action' => 'cart_cleaned_after_order',
        ]);
    }
}
