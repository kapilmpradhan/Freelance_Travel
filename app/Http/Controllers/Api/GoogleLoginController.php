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
            // Find the position of the first space to separate first and last name from full name
            $spacePosition = strpos($name, ' ');
            $data = [
                'first_name' => substr($googleUser->name, 0, $spacePosition),
                'last_name' => substr($googleUser->name, $spacePosition + 1),
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
