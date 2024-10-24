<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use App\Services\AppleService; // You'll need to create this service for Apple-specific token verification
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Api\BaseController;

class AppleLoginController extends BaseController
{
    public function userLoginApple(Request $request, User $user, JwtService $jwtService, AppleService $appleService)
    {
        $data = $request->all();
        if (!isset($data['apple_identity_token'])) {
            return $this->sendError('Authentication error', null, 401);
        }

        // Verify Apple's identity token
        $googleClientIds = explode(',', env('APPLE_CLIENT_IDS'));
        $tokenInfo = $appleService->verifyIdentityToken($data['apple_identity_token']);
        if (!isset($tokenInfo['aud']) || !in_array($tokenInfo['aud'], $googleClientIds)) {
            return $this->sendError('Authentication error: Unauthorized client', null, 401);
        }

        $userInfo = $appleService->getUserInfo($data['apple_identity_token']);
        if (!$userInfo) {
            return $this->sendError('Authentication error', null, 401);
        }

        $first_name = $userInfo['first_name'] ?? null;
        $last_name = $userInfo['last_name'] ?? null;
        $email = $userInfo['email'];

        // Get existing user by email
        $existing_user = User::where('email', $email)->first();

        if (!$existing_user) {
            $data = [
                'first_name' =>  $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'is_email_verified' => true,
                'sso_type' => env('SSO_TYPE_APPLE', 'apple')
            ];
            $new_user = $user->storeUser($data);
        }

        $user = ($existing_user) ? $existing_user : $new_user;

        $data = [
            "accessToken" => $jwtService->generateToken($user, 'access'),
            "refreshToken" => $jwtService->generateToken($user, 'refresh')
        ];

        return $this->sendResponse($data, 'successfully');
    }
}
