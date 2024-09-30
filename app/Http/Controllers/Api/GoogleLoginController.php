<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use App\Services\GoogleService;
use App\Http\Resources\UserResource;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Api\BaseController;

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
                'sso_type' => env('SSO_TYPE_GOOGLE', 'google')

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

    public function userLoginGoogle(Request $request, User $user, JwtService $jwtService, GoogleService $googleService)
    {
        $data = $request->all();
        if (!$data['google_access_token']) {
            return $this->sendError('Authentication error', null, 401);
        }

        $userInfo = $googleService->googleUserDetail($data['google_access_token']);

        if (!$userInfo) {
            return $this->sendError('Authentication error', null, 401);
        }

        $name = $userInfo['name'];
        $email = $userInfo['email'];

        $existing_user = User::where('email', $email)->first();

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
                'sso_type' => env('SSO_TYPE_GOOGLE', 'google')

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
