<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_items';
    protected $fillable = ['user_id', 'tdms_product_id', 'product_price_details_id', 'booking_date', 'time_id'];


    /**
     * Relationship: Each cartItem belongs to a single user.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'tdms_product_id', 'tdms_product_id');
    }

    public function productPriceAvailability()
    {
        return $this->belongsTo(
            ProductPriceAvailability::class,
            'product_price_details_id', // Foreign key in cart_items
            'product_price_details_id'  // Referenced key in product_price_availabilities
        )->whereColumn(
            'cart_items.tdms_product_id', // Ensure tdms_product_id also matches
            'product_price_availabilities.tdms_product_id'
        );
    }

    protected function bookingDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Carbon::parse($value)->format('d-M-Y'),
            set: fn ($value) => Carbon::parse($value)->format('Y-m-d')
        );
    }

    public function addProductsToCartRule()
    {
        return [
            'tdms_product_id' => 'required|int',
            'product_price_details_id' => 'required|int',
            'booking_date' => 'required|date_format:j-M-Y',
            'time_id' => 'required|string'
        ];
    }

    public function storeCartItem($user, $data)
    {
        return $this->create($data);
    }
}
