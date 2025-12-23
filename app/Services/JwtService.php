<?php

namespace App\Services;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Str;
use App\Models\RefreshToken;
use App\Logging\Logger;

class JwtService
{
    public static function generateAccessToken(User $user)
    {
        $exp = config('vars.access_token_validity_period_in_minutes');
        $payload = [
            'iss' => 'access',
            'sub' => $user->uuid,
            'iat' => time(),
            'exp' => time() + 60 * $exp
        ];

        Logger::debug('Access token generated', [
            'log_file' => config('logging.log_files.user_jwt'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'access_token_generated',
        ]);

        return JWT::encode(
            $payload,
            config('app.key'),
            config('vars.jwt_token_encrypt_algorithm')
        );
    }

    public function generateRefreshToken($userId, $userAgent)
    {
        $randString = Str::random(128);
        $last_used_at = Carbon::now();

        $refreshToken = RefreshToken::create([
            "user_id" => $userId,
            "token" => $randString,
            "last_used_at" => $last_used_at,
            "user_agent" => $userAgent
        ]);

        Logger::debug('Refresh token generated', [
            'log_file' => config('logging.log_files.user_jwt'),
            'user_id' => $userId,
            'action' => 'refresh_token_generated',
        ]);

        return $refreshToken->token;
    }

    // Validate and decode JWT token
    public function validateAccessToken($token)
    {
        if (!$token) {
            Logger::debug('Access token validation failed - no token', [
                'log_file' => config('logging.log_files.user_jwt'),
                'action' => 'access_token_missing',
            ]);

            return [
                'user' => null,
                'error' => 'Token not provided'
            ];
        }
        try {
            $payload = JWT::decode(
                $token,
                new Key(
                    config('app.key'),
                    config('vars.jwt_token_encrypt_algorithm')
                )
            );

            Logger::debug('Access token validated', [
                'log_file' => config('logging.log_files.user_jwt'),
                'user_id' => $payload->sub,
                'action' => 'access_token_validated',
            ]);

            return [
                'tokenType' => $payload->iss,
                'user' => User::find($payload->sub),
                'error' => null
            ];
        } catch (ExpiredException $e) {
            Logger::debug('Access token validation failed - expired', [
                'log_file' => config('logging.log_files.user_jwt'),
                'action' => 'access_token_expired',
            ]);

            return [
                'user' => null,
                'error' => 'Token has expired.'
            ];
        } catch (Exception $e) {
            Logger::debug('Access token validation failed - invalid', [
                'log_file' => config('logging.log_files.user_jwt'),
                'action' => 'access_token_invalid',
            ]);

            return [
                'user' => null,
                'error' => 'Token is invalid.'
            ];
        }
    }

    public function validateRefreshToken($token, $userId)
    {
        $refreshToken = RefreshToken::where('token', $token)
                                    ->where('user_id', $userId)
                                    ->first();
        if (!$refreshToken) {
            Logger::debug('Refresh token validation failed - not found', [
                'log_file' => config('logging.log_files.user_jwt'),
                'user_id' => $userId,
                'action' => 'refresh_token_not_found',
            ]);

            return false;
        }

        $lastUsed = $refreshToken->last_used_at;
        $twoWeeksAgo = Carbon::now()->subWeeks(2);
        if (Carbon::parse($lastUsed)->lessThan($twoWeeksAgo)) {
            Logger::debug('Refresh token validation failed - expired', [
                'log_file' => config('logging.log_files.user_jwt'),
                'user_id' => $userId,
                'action' => 'refresh_token_expired',
            ]);

            return false;
        } else {
            $refreshToken->last_used_at = Carbon::now();
            $refreshToken->save();
        }

        $user = User::where('uuid', $refreshToken->user_id)->first();

        Logger::debug('Refresh token validated - new access token generated', [
            'log_file' => config('logging.log_files.user_jwt'),
            'user_id' => $userId,
            'action' => 'refresh_token_validated',
        ]);

        $newAccessToken = JwtService::generateAccessToken($user);
        return $newAccessToken;
    }
}
