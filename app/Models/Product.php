<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $fillable = ['product_id', 'version', 'json'];
    protected $casts = [
        'json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // Fetch the latest product based on the 'product_id'
            $latestProduct = static::where('product_id', $product->product_id)
                ->orderBy('version', 'desc')
                ->first();

            if ($latestProduct) {
                $product['version'] = $latestProduct->version + 1;
            }
        });
    }
}
