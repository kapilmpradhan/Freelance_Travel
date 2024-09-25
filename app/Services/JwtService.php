<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;

class JwtService
{
    protected $secretKey;
    protected $algo;
    protected $tokenValidityInMinutes;

    public function __construct()
    {
        $this->secretKey = Config::get('jwt.secret');
        $this->algo = 'HS256';  // Set the algorithm
        $this->tokenValidityInMinutes = Config::get('jwt.token_validity');  // Get from config
    }

    /**
     * Generate JWT Token
     */
    public function generateToken($payload)
    {
        $payload['exp'] = time() + ($this->tokenValidityInMinutes * 60); // Expiry in minutes
        return JWT::encode($payload, $this->secretKey, $this->algo);
    }

    /**
     * Decode JWT Token
     */
    public function decodeToken($token)
    {
        try {
            return JWT::decode($token, new Key($this->secretKey, $this->algo));
        } catch (\Exception $e) {
            throw new \Exception('Token is invalid or expired');
        }
    }

    /**
     * Validate Token
     */
    public function validateToken($token)
    {
        try {
            $decoded = $this->decodeToken($token);
            return (array)$decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
}
