<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentToken extends Model
{
    use HasFactory;

    protected $table = 'agent_tokens';
    protected $fillable = ['user_id', 'username', 'access_token', 'expires_in', 'token_type', 'scope', 'bank_bsb', 'bank_account', 'bank_country_short_code', 'business_number', 'trading_name'];

    public function addAgentTokenRule()
    {
        return [
            'user_id' => 'required|string',
            'username' => 'required|email|unique:agent_tokens,username',
            'access_token' => 'required|string',
            'expires_in' => 'required|integer',
            'token_type' => 'required|string',
            'scope' => 'required|string',
            'bank_bsb' => 'nullable|string',
            'bank_account' => 'nullable|string',
            'bank_country_short_code' => 'nullable|string',
            'business_number' => 'nullable|string',
            'trading_name' => 'nullable|string'
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
