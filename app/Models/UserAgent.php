<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property int|null $user_id
 * @property int|null $agent_id
 * @property string $type
 * @property string|null $email
 * @property string|null $password
 * @property string|null $access_token
 * @property string $status
 * @property int|null $expires_in
 * @property string|null $token_type
 * @property string|null $scope
 * @property string|null $bank_bsb
 * @property string|null $bank_account
 * @property string|null $bank_country_short_code
 * @property string|null $business_number
 * @property string|null $trading_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $is_active
 * @property bool $is_deleted
 * @property string $branch_code
 * @property int|null $referral_source_id
 * @property int|null $points_balance
 * @property int|null $points_available
 * @property int|null $points_multiplier
 */
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
        'is_deleted',
        'points_balance',
        'points_available',
        'points_multiplier',
    ];

    protected $casts = [
        'referral_source_id' => 'integer',
    ];

    protected $hidden = ['password'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function getActiveAgent($userId): ?UserAgent
    {
        return $this::where('user_id', $userId)
                    ->where('type', 'integration')
                    ->where('is_active', true)
                    ->where('is_deleted', false)
                    ->orderByDesc('created_at')
                    ->first();
    }

    public function getDefaultAgent(): ?UserAgent
    {
        return $this::where('branch_code', config('vars.default_agent_branch_code'))
                    ->where('email', config('vars.default_agent_email'))
                    ->orderBy('created_at', 'desc')
                    ->first();
    }
}
