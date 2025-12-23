<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use App\Services\GoogleService;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Api\BaseController;
use App\Logging\Logger;

class GoogleLoginController extends BaseController
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }


    public function handleGoogleCallback(User $user, JwtService $jwtService)
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $existing_user = User::where('email', $googleUser->email)->first();

        if (!$existing_user) {
            $name = $googleUser->name;
            // Handle if name from google doesnot not contain first/last name
            if ($name) {
                $exploded_name = explode(' ', $name);
                $first_name = $exploded_name[0];
                $last_name = implode('', array_slice($exploded_name, 1));
            }
            $data = [
                'first_name' => ($name) ? $first_name : null,
                'last_name' => ($name) ? $last_name : null,
                'email' => $googleUser->email,
                'is_email_verified' => true,
                'sso_type' => config('vars.sso_type_google')

            ];
            $new_user = $user->storeUser($data);
        }

        $user = ($existing_user) ? $existing_user : $new_user;

        $data = [
            "accessToken" => $jwtService->generateAccessToken($user),
            "refreshToken" => $jwtService->generateRefreshToken($user->uuid, null)
        ];

        return $this->sendResponse('JWT tokens', $data);
    }

    public function userLoginGoogle(Request $request, User $user, JwtService $jwtService, GoogleService $googleService)
    {
        $data = $request->all();
        if (!$data['google_access_token']) {
            return $this->sendError('Authentication error', null, 401);
        }

        // Verify the token's client ID to ensure it's from your app
        $googleClientIds = explode(',', config('vars.google_client_ids'));
        $tokenInfo = $googleService->googleTokenDetail(($data['google_access_token']));
        if (!isset($tokenInfo['aud']) || !in_array($tokenInfo['aud'], $googleClientIds)) {
            return $this->sendError('Authentication error: Unauthorized client', null, 401);
        }

        $userInfo = $googleService->googleUserDetail($data['google_access_token']);
        if (!$userInfo) {
            return $this->sendError('Authentication error', [], 401);
        }

        $name = $userInfo['name'];
        $email = $userInfo['email'];

        $existing_user = User::getAllUsers()
                        ->where('email', $email)
                        ->first();

        if ($existing_user && $existing_user->is_permanently_deleted) {
            return $this->sendError('This account was deleted permnently');
        }

        if ($existing_user && $existing_user->is_temporarily_deleted) {
            $existing_user->is_temporarily_deleted = false;
            $existing_user->deletion_date = null;
        }

        if ($existing_user) {
            $existing_user->last_login = now();
            $existing_user->save();
        }

        if (!$existing_user) {
            if ($name) {
                $exploded_name = explode(' ', $name);
                $first_name = $exploded_name[0];
                $last_name = implode('', array_slice($exploded_name, 1));
            }
            $data = [
                'first_name' => ($name) ? $first_name : null,
                'last_name' => ($name) ? $last_name : null,
                'email' => $email,
                'is_email_verified' => true,
                'sso_type' => config('vars.sso_type_google'),
                'profile_status' => 'success'
            ];
            $new_user = User::create($data);
        }

        $user = ($existing_user) ? $existing_user : $new_user;

        $data = [
            "accessToken" => $jwtService->generateAccessToken($user),
            "refreshToken" => $jwtService->generateRefreshToken(
                $user->uuid,
                $request->header('User-Agent')
            )
        ];

        $action = $existing_user ? 'login_google' : 'signup_google';
        Logger::debug('User ' . ($existing_user ? 'login' : 'signup') . ' via Google', [
            'log_file' => config('logging.log_files.user_activity'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => $action,
            'platform' => app('platform'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return $this->sendResponse('JWT tokens', $data);
    }
}
