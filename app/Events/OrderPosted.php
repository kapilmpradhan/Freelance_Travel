<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPosted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $bookingReference;
    public $cartItemIds;
    public $emailData; // can be null if intent is not email-quote
    public $intent; // intent order was created: email-quote or pay-now
    public $responseData;
    public $requestData;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct(
        $userId,
        $bookingReference,
        $cartItemIds,
        $requestData,
        $responseData,
        $intent,
        $emailData,
    ) {
        $this->bookingReference = $bookingReference;
        $this->cartItemIds = $cartItemIds;
        $this->emailData = $emailData;
        $this->intent = $intent;
        $this->responseData = $responseData;
        $this->requestData = $requestData;
        $this->userId = $userId;
    }
}
