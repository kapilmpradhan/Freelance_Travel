<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';
    protected $fillable = ['user_id', 'product_id', 'product_price_id', 'booking_datetime'];


    /**
     * Relationship: Each cartItem belongs to a single user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function addProductsToCartRule()
    {
        return [
            'product_id' => 'required|int',
            'product_price_id' => 'required|int',
            'booking_datetime' => 'required|date_format:Y-m-d H:i:s'
        ];
    }
}
