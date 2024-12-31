<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAgent extends Model
{
    use HasFactory;

    protected $table = 'user_agents';
    protected $fillable = [
        'agent_id',
        'user_id',
        'type',
        'email',
        'password',
        'status',
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

    protected $hidden = ['password'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
}
