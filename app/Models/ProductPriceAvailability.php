<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPriceAvailability extends Model
{
    use HasFactory;

    protected $table = 'product_price_availabilities';
    protected $fillable = ['tdms_product_id', 'product_price_details_id', 'booking_details', 'json'];

    protected $casts = [
        'json' => 'array',
        'booking_details' => 'array',
    ];
}
