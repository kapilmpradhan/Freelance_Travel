<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class AppleService
{
    public static function getUserInfo($data)
    {

        // Extract the unverified header from the JWT token
        $jwtParts = explode('.', $data['access_token']);
        if (count($jwtParts) < 2) {
            return ['success' => false, 'errors' => ['Invalid token format']];
        }

        try {
            $tokenData = json_decode(base64_decode($jwtParts[1]), true);
            $knownClients = explode(',', Config::get('services.apple.client_id'));

            if ($tokenData['aud'] == $knownClients) {
                return ['success' => false, 'errors' => 'Invalid token'];
            }

            return [
                'success' => true,
                'data' => [
                    'email' => $tokenData['email'],
                    'first_name' => $tokenData['first_name'] ?? null,
                    'last_name' => $tokenData['last_name'] ?? null
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }
}
