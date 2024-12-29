<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItemAvailability extends Model
{
    use HasFactory;

    protected $table = 'cart_item_availabilities';
    protected $fillable = [
        'cart_item_id',
        'start_date',
        'days',
        'selected_index',
        'pax',
    ];

    protected $casts = [
        'selected_indices' => 'array',
        'json_result' => 'array',
    ];

    public function cartItem()
    {
        return $this->belongsTo(CartItem::class, 'cart_item_id');
    }
}
