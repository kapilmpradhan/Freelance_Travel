<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';
    protected $fillable = [
        'tdms_product_id',
        'version',
        'schema_version',
        'counter',
        'tdms_product_last_update_date',
        'json'
    ];
    protected $casts = [
        'json' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            DB::transaction(function () use ($product) {
                // Fetch the latest product based on the 'tdms_product_id'
                $latestProduct = static::where('tdms_product_id', $product->tdms_product_id)
                    ->orderBy('version', 'desc')
                    ->first();

                if ($latestProduct) {
                    // Set the new product's version to the latest + 1
                    $product['version'] = $latestProduct->version + 1;
                } else {
                    // If this is the first product for the given 'tdms_product_id'
                    $product['version'] = 1;
                }
            });
        });
    }
}
