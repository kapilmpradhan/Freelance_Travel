<?php

namespace App\Services;

use App\Logging\Logger;
use Illuminate\Support\Facades\Http;

class GoogleService
{
    public function googleTokenDetail($googleToken)
    {
        $response = Http::withToken($googleToken)->get('https://oauth2.googleapis.com/tokeninfo');
        if ($response->successful()) {
            Logger::debug('Google token validated', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'google_token_valid',
            ]);

            return $response->json();
        }

        Logger::debug('Google token validation failed', [
            'log_file' => config('logging.log_files.user_auth'),
            'action' => 'google_token_invalid',
        ]);

        return false;
    }

    public function googleUserDetail($accessToken)
    {
        if (config('app.env') == 'testing') {
            return[
                'name' => 'Test User',
                'email' => 'test@user.com'
            ];
        } else {
            $response = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
            if ($response->successful()) {
                $userData = $response->json();

                Logger::debug('Google user info retrieved', [
                    'log_file' => config('logging.log_files.user_auth'),
                    'user_email' => $userData['email'] ?? 'unknown',
                    'action' => 'google_user_info_success',
                ]);

                return $userData;
            } else {
                Logger::debug('Google user info retrieval failed', [
                    'log_file' => config('logging.log_files.user_auth'),
                    'action' => 'google_user_info_failed',
                ]);

                return false;
            }
        }
    }
}
