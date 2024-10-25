<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Exception;
use Illuminate\Support\Facades\Http;

class AppleService
{
    protected $appleKeyUrl = 'https://appleid.apple.com/auth/keys';

    public function getTokenInfo($identityToken)
    {
        $parts = explode('.', $identityToken);

        if (count($parts) !== 3) {
            return false;
        }

        // Decode the  payload
        $payload = json_decode(base64_decode($parts[1]), true);

        return $payload;
    }
}
