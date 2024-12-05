<?php

namespace App\Models;

use Carbon\Carbon;
use App\Jobs\CacheProductJob;
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

    public function storeCartItem($data)
    {
        $cartItem = $this->create($data);
        CacheProductJob::dispatch($cartItem);

        return $cartItem;
    }
}
