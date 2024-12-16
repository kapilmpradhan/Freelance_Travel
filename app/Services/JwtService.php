<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Firebase\JWT\ExpiredException;
use PhpOption\None;

class JwtService
{
    protected $secretKey;
    protected $algorithm;

    public function __construct()
    {
        $this->secretKey = config('app.key');
        $this->algorithm = config('vars.jwt_token_encrypt_algorithm');
    }

    // Generate JWT token
    public function generateToken(User $user, $tokenType)
    {
        if ($tokenType == 'access') {
            $exp = config('vars.access_token_validity_period_in_minutes');
        } elseif ($tokenType == 'refresh') {
            $exp = config('vars.refresh_token_validity_period_in_minutes');
        }
        $payload = [
            'iss' => $tokenType,
            'sub' => $user->uuid,
            'iat' => time(),
            'exp' => time() + 60 * $exp
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    // Validate and decode JWT token
    public function validateToken($token)
    {
        if (!$token) {
            return [
                'user' => null,
                'error' => 'Token not provided'
            ];
        }
        try {
            $payload = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return [
                'tokenType' => $payload->iss,
                'user' => User::find($payload->sub),
                'error' => null
            ];
        } catch (ExpiredException $e) {
            return [
                'user' => null,
                'error' => 'Token has expired.'
            ];
        } catch (Exception $e) {
            return [
                'user' => null,
                'error' => 'Token is invalid.'
            ];
        }
    }
}
