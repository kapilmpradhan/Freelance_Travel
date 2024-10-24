<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class AppleService
{
    protected $applePublicKeyUrl = 'https://appleid.apple.com/auth/keys';

    /**
     * Verifies the Apple identity token (JWT) using Apple's public keys
     */
    public function verifyIdentityToken($identityToken)
    {
        // Fetch Apple's public keys
        $response = Http::get($this->applePublicKeyUrl);
        if (!$response->successful()) {
            return false;
        }

        $publicKeys = $response->json();
        
        try {
            // Decode the identity token using Apple's JWK keys
            $decoded = JWT::decode($identityToken, JWK::parseKeySet($publicKeys), ['RS256']);

            return (array) $decoded; // Return the decoded token details
        } catch (\Exception $e) {
            // Handle token verification errors (invalid token, etc.)
            return false;
        }
    }

    /**
     * Extracts user information from the identity token
     */
    public function getUserInfo($identityToken)
    {
        // Apple's identity token is a JWT and contains user info like email and name
        $decodedToken = $this->verifyIdentityToken($identityToken);
        if (!$decodedToken) {
            return false;
        }

        $userInfo = [
            'email' => $decodedToken['email'] ?? null,
            'first_name' => $decodedToken['name']['first_name'] ?? null,
            'last_name' => $decodedToken['name']['last_name'] ?? null
        ];

        return $userInfo;
    }
}
