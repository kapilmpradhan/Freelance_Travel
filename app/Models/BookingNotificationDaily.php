<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingNotificationDaily extends Model
{
    use HasFactory;

    protected $table = 'booking_notification_daily';
    protected $fillable = [
        'booking_notification_id',
        'user_order_id',
        'cart_item_id',
        'booking_time',
        'notify_to_email',
        'is_notified'
    ];
}
