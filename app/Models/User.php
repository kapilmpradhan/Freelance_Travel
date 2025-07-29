<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property-read string $uuid
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string|null $verified_email
 * @property string|null $password
 * @property bool $is_email_verified
 * @property string $sso_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property bool $is_ops
 * @property string $profile_status
 * @property string|null $date_of_birth
 * @property string|null $phone_number
 * @property string|null $post_code
 * @property string|null $country_code
 * @property string|null $title
 * @property string|null $deletion_date
 * @property bool $is_temporarily_deleted
 * @property bool $is_permanently_deleted
 * @property string|null $nickname
 * @property string|null $last_login
 * @property bool $is_points_displayed
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasUuids;

    protected $table = 'users';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'email',
        'verified_email',
        'password',
        'is_email_verified',
        'sso_type',
        'is_ops',
        'profile_status',
        'date_of_birth',
        'phone_number',
        'post_code',
        'country_code',
        'deletion_date',
        'last_login',
        'is_temporarily_deleted',
        'is_permanently_deleted',
        'is_points_displayed',
    ];
    protected $casts = [
        'is_email_verified' => 'boolean',
    ];

    protected function dateOfBirth(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->format('d-M-Y') : null,
            set: fn ($value) => $value ? Carbon::parse($value)->format('Y-m-d') : null
        );
    }

    /**
     * Define a one-to-one relationship with Agent Token.
     */
    public function agentToken()
    {
        return $this->hasOne(AgentToken::class, 'user_id', 'uuid');
    }

    public function emailSignupRule()
    {
        return [
            'first_name' => [
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            'last_name' => [
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            'email' => 'required|email|unique:users,email|max:100',
            'password' => 'required|string|min:8',
            'is_email_verfied' => 'boolean',
        ];
    }

    public function emailLoginRule()
    {
        return [
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:8'
        ];
    }

    public function changePasswordRule()
    {
        return [
            'current_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8'
        ];
    }

    public function forgotPasswordRule()
    {
        return [
            'email' => 'required|email'
        ];
    }

    public function verifyOtpRule()
    {
        return [
            'email' => 'required|email',
            'otp' => 'required'
        ];
    }

    public function resetPasswordRule()
    {
        return [
            'email' => 'required|email',
            'otp' => 'required',
            'new_password' => 'required|string|min:8'
        ];
    }

    public function updateProfileRule()
    {
        return [
            "title" => "in:Master,Mr,Miss,Mrs,Ms,Mx",
            'first_name' => [
                'required',
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            'last_name' => [
                'required',
                'string',
                'max:200',
                'regex:' . config('vars.only_char_regex')
            ],
            "date_of_birth" => "required|date_format:d-M-Y",
            "phone_number" => "required|string",
            "post_code" => "required|string",
            "country_code" => "required|string"
        ];
    }

    public function storeUser($data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $data['profile_status'] = 'success';
        $data['is_email_verified'] = false;
        $user = $this->create($data);
        return $user;
    }

    public function updatePassword($new_password)
    {
        if ($new_password) {
            $this->update(['password' => Hash::make($new_password)]);
        }
    }

    public function getSsoEmailUser($email)
    {
        return $this->where('email', $email)
                    ->where('sso_type', 'email')
                    ->first();
    }

    public function fcmTokens()
    {
        return $this->hasMany(FirebaseFcmToken::class, 'user_id', 'uuid')->pluck('token')->toArray();
    }

    public static function getAllUsers()
    {
        return User::all();
    }

    public static function getTemporarilyDeletedUsers()
    {
        return User::where('is_temporarily_deleted', true)
                ->where('is_permanently_deleted', false);
    }
}
