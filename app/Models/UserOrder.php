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
        'cart_item_ids', // needed to
        'user_id',
        'request_data',
        'response_data',
    ];
    protected $casts = [
        'cart_item_ids' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
    ];
}
