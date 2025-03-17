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

    public $userOrderId;

    /**
     * Create a new event instance.
     */
    public function __construct($userOrderId)
    {
        $this->userOrderId = $userOrderId;
    }
}
