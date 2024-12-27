<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductHistory extends Model
{
    use HasFactory;

    protected $table = 'product_histories';
    protected $fillable = ['tdms_product_id', 'version', 'tdms_product_last_update_date', 'json'];
    protected $casts = [
        'json' => 'array',
    ];
}
