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
use App\Services\UserService;

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
        return $refreshToken->token;
    }

    // Validate and decode JWT token
    public function validateAccessToken($token)
    {
        if (!$token) {
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

    public function validateRefreshToken($token, $userId)
    {
        $refreshToken = RefreshToken::where('token', $token)
                                    ->where('user_id', $userId)
                                    ->first();
        if (!$refreshToken) {
            return false;
        }

        $lastUsed = $refreshToken->last_used_at;
        $twoWeeksAgo = Carbon::now()->subWeeks(2);
        if (Carbon::parse($lastUsed)->lessThan($twoWeeksAgo)) {
            return false;
        } else {
            $refreshToken->last_used_at = Carbon::now();
            $refreshToken->save();
        }

        $user = User::where('uuid', $refreshToken->user_id)->first();
        $newAccessToken = JwtService::generateAccessToken($user);
        return $newAccessToken;
    }
}
