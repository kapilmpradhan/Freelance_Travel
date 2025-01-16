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
        'branch_code',
        'status',
        'access_token',
        'expires_in',
        'token_type',
        'scope',
        'bank_bsb',
        'bank_account',
        'bank_country_short_code',
        'business_number',
        'trading_name',
        'is_active',
        'is_deleted'
    ];

    protected $hidden = ['password'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function getActiveAgent($userId)
    {
        return $this::where('user_id', $userId)
                    ->where('type', 'integration')
                    ->where('is_active', true)
                    ->where('is_deleted', false)
                    ->orderByDesc('created_at')
                    ->first();
    }

    public function getDefaultAgent()
    {
        return $this::where('branch_code', config('vars.default_agent_branch_code'))
                    ->where('email', config('vars.default_agent_email'))
                    ->orderBy('created_at', 'desc')
                    ->first();
    }
}
