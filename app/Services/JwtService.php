<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Firebase\JWT\ExpiredException;

class JwtService
{
    protected $secretKey;

    public function __construct()
    {
        $this->secretKey = env(ENV_KEY_APP_KEY);
    }

    // Generate JWT token
    public function generateToken(User $user, $tokenType)
    {
        if ($tokenType = 'access') {
            $exp = env(ENV_KEY_ACCESS_TOKEN_VALIDITY_PERIOD_IN_MINUTES, 10);
        } elseif ($tokenType = 'refresh') {
            $exp = env(ENV_KEY_REFRESH_TOKEN_VALIDITY_PERIOD_IN_MINUTES, 1000);
        }
        $payload = [
            'iss' => env(ENV_KEY_APP_NAME),
            'sub' => $user->uuid,
            'iat' => time(),
            'exp' => time() + 60 * $exp
        ];

        return JWT::encode($payload, $this->secretKey, env(ENV_KEY_JWT_TOKEN_ENCRYPT_ALGORITHM));
    }

    // Validate and decode JWT token
    public function validateToken($token)
    {
        try {
            $payload = JWT::decode($token, new Key($this->secretKey, env(ENV_KEY_JWT_TOKEN_ENCRYPT_ALGORITHM)));
            return User::find($payload->sub);
        } catch (ExpiredException $e) {
            throw new Exception('Token has expired.');
        } catch (Exception $e) {
            throw new Exception('Token is invalid.');
        }
    }
}
