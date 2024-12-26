<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentDetail extends Model
{
    use HasFactory;

    protected $table = 'agent_details';
    protected $fillable = [
        'email',
        'access_token',
        'expires_in',
        'token_type',
        'scope',
        'bank_bsb',
        'bank_account',
        'bank_country_short_code',
        'business_number',
        'trading_name'
    ];
}
