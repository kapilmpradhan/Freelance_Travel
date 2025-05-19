<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserOrder extends Model
{
    use HasFactory;

    protected $table = 'user_orders';
    protected $fillable = [
        'booking_reference',
        'order_id',
        'user_agent_id',
        'cart_item_ids', // needed to
        'is_paid',
        'intent',
        'user_id',
        'request_data',
        'response_data',
        'payment_gateway',
        'applied_discount',
    ];
    protected $casts = [
        'cart_item_ids' => 'array',
        'is_paid' => 'boolean',
        'request_data' => 'array',
        'response_data' => 'array',
        'payment_gateway' => 'array'
    ];
}
