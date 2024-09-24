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

    protected $table = 'accounts';
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    protected $fillable = ['first_name', 'last_name', 'email', 'password', 'is_email_verified', 'sso_type'];

    public function signupRule()
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:accounts,email|max:100',
            'password' => 'required|string|min:8',
            'is_email_verfied' => 'boolean',
            'sso_type'  => 'in:' . implode(',', [
                self::env('SSO_TYPE_EMAIl'),
                self::env('SSO_TYPE_GOOGLE'),
                self::env('SSO_TYPE_APPLE'),
            ]),
        ];
    }

    public function loginRule()
    {
        return [
            'email' => 'required|email|max:100',
            'password' => 'required|string|min:8'
        ];
    }

    public function storeAccount($request)
    {
        $params = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_email_verified' => false,
            'sso_type' => $request->sso_type
        ];
        return $this->create($params);
    }
}
