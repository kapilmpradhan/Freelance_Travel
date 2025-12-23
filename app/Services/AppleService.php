<?php

namespace App\Services;

use App\Logging\Logger;
use Illuminate\Support\Facades\Config;

class AppleService
{
    public static function getUserInfo($data)
    {
        // Extract the unverified header from the JWT token
        $jwtParts = explode('.', $data['access_token']);
        if (count($jwtParts) < 2) {
            Logger::debug('Apple token validation failed - invalid format', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'apple_token_invalid_format',
            ]);

            return ['success' => false, 'errors' => ['Invalid token format']];
        }

        try {
            $tokenData = json_decode(base64_decode($jwtParts[1]), true);
            $knownClients = explode(',', Config::get('services.apple.client_id'));

            if ($tokenData['aud'] == $knownClients) {
                Logger::debug('Apple token validation failed - invalid client', [
                    'log_file' => config('logging.log_files.user_auth'),
                    'action' => 'apple_token_invalid_client',
                ]);

                return ['success' => false, 'errors' => 'Invalid token'];
            }

            Logger::debug('Apple user info retrieved', [
                'log_file' => config('logging.log_files.user_auth'),
                'user_email' => $tokenData['email'] ?? 'unknown',
                'action' => 'apple_user_info_success',
            ]);

            return [
                'success' => true,
                'data' => [
                    'email' => $tokenData['email']
                ]
            ];
        } catch (\Exception $e) {
            Logger::error(
                'Apple token validation exception',
                $e,
                null,
                [
                    'log_file' => config('logging.log_files.errors'),
                    'action' => 'apple_token_exception',
                ]
            );

            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }
}
