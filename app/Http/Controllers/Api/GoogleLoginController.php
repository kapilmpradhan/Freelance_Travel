<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\UserResource;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends BaseController
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }


    public function handleGoogleCallback(User $user, UserResource $userResource)
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
        $data = $userResource->userDetail(($existing_user) ? $existing_user : $new_user);

        return $this->sendResponse($data, 'successfully');
    }
}
