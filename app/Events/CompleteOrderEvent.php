<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompleteOrderEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $userOrder;
    public $cartItems;

    /**
     * Create a new event instance.
     */
    public function __construct($userOrder, $cartItems)
    {
        $this->userOrder = $userOrder;
        $this->cartItems = $cartItems;
    }
}
