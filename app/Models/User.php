<?php

namespace App\Models;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'users';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'is_email_verified', 'sso_type'];

    public function emailSignupRule()
    {
        return [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
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

    public function updatePasswordRule()
    {
        return [
            'email' => 'required|email',
            'otp' => 'required',
            'new_password' => 'required|string|min:8'
        ];
    }


    public function storeUser($data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        return $this->create($data);
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
}
