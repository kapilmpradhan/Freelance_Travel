<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Fareprice extends Model
{
    use HasFactory;

    protected $table = 'fareprices';
    protected $fillable = [
        'tdms_product_id',
        'json',
        'agent_branch',
        'product_version'
    ];

    protected $casts = ['json' => 'array'];
}
