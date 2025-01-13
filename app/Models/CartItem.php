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
    protected $fillable = [
        'user_id',
        'tdms_product_id',
        'product_price_details_id',
        'time_id',
        'booking_date',
        'start_date',
        'days',
        'selected_index',
        'availability',
        'availability_last_updated_at',
        'booking_details',
        'booking_quantity',
        'booking_data',
        'user_order_id',
    ];
    protected $casts = [
        'availability' => 'array',
        'booking_details' => 'array',
        'booking_data' => 'array',
    ];

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

    protected function startDate(): Attribute
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

    public static function saveItemsRule()
    {
        return [
            'tdmsProductId' => 'required|integer',
            'productPricesDetailsId' => 'required|integer',
            'timeId' => 'required|string',
            // Must be in format 30-Nov-2012
            'startDate' => 'required|date|date_format:d-M-Y',
            // Must be greater than zero
            'days' => 'required|integer|min:1',
             // Must be an array with at least one element
            'selectedAvailableIndices' => 'required|array|min:1',
            // Each element in the array must be an integer greater than or equal to 0
            'selectedAvailableIndices.*' => 'integer|min:0',
        ];
    }

    public static function updateItemBookingDataRule()
    {
        return [
            'quantity' => 'required|integer',
            'bookingData' => 'array'
        ];
    }

    public function storeCartItem($user, $data)
    {
        return $this->create($data);
    }

    public static function userCartItems($userId)
    {
        return CartItem::where('user_id', $userId)
            // only return items added from new api
            ->whereNotNull('selected_index')
            // filter processed items
            ->whereNull('user_order_id');
    }
}
