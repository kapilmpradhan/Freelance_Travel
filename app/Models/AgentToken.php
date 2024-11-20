<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentToken extends Model
{
    use HasFactory;

    protected $table = 'agent_tokens';
    protected $fillable = [
        'user_id',
        'type',
        'username',
        'password',
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

    public function addAgentTokenRule()
    {
        return [
            'user_id' => 'required|uuid',
            'username' => 'required|email',
            'password' => 'required|string'
        ];
    }

    public function updateAgentTokenRule()
    {
        return [
            'username' => 'required|email',
            'password' => 'required|string'
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
