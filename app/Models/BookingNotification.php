<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingNotification extends Model
{
    use HasFactory;

    protected $table = 'booking_notifications';
    protected $fillable = [
        'user_order_id',
        'cart_item_id',
        'booking_date',
        'booking_time',
        'notify_to_email',
        'is_completed'
    ];
}
