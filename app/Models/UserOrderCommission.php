<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOrderCommission extends Model
{
    use HasFactory;

    protected $table = 'user_order_commissions';
    protected $fillable = [
        'user_id',
        'user_order_id',
        'agent_branch',
        'is_cart',
        'quote_id',
        'is_direct_purchase',
        'percentage',
    ];

    protected $casts = [
        'user_id' => 'string',
        'is_cart' => 'boolean',
        'is_direct_purchase' => 'boolean',
        'percentage' => 'float'
    ];
}
