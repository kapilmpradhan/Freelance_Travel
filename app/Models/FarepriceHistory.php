<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FarepriceHistory extends Model
{
    use HasFactory;

    use HasFactory;

    protected $table = 'fareprice_histories';
    protected $fillable = [
        'tdms_product_id',
        'json',
        'agent_branch',
        'version'
    ];

    protected $casts = ['json' => 'array'];
}
