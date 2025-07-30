<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property string $user_id
 * @property int|null $user_order_id
 * @property string|null $agent_branch
 * @property bool $is_cart
 * @property string|null $quote_id
 * @property bool $is_direct_purchase
 * @property float|null $percentage
 * @property int|null $points_available
 * @property string|null $created_at
 * @property string|null $updated_at
 */
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
