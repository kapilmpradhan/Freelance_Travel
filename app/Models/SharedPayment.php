<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property string $email
 * @property int $quote_id
 * @property int|null $redeemer_id
 * @property string|null $payment_link
 * @property boolean $is_latest
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class SharedPayment extends Model
{
    use HasFactory;

    protected $table = 'shared_payments';
    protected $fillable = [
        'name',
        'email',
        'quote_id',
        'redeemer_id',
        'payment_link',
        'is_latest'
    ];
    protected $casts = [
        'is_latest' => 'boolean'
    ];
}
