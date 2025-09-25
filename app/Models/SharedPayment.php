<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
