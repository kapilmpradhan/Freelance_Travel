<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    public function userSignupEmail(Request $request, User $user, UserResource $userResource)
    {
        $data = $request->all(); // Retrive request data
        $validate = Validator::make($data, $user->emailSignupRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $data['sso_type'] = 'email'; // Add email sso to the array
        $new_user = $user->storeUser($data);
        $return_data = $userResource->userDetail($new_user);

        return $this->sendResponse($return_data, 'successfully');
    }

    public function userLoginEmail(Request $request, User $user, JwtService $jwtService)
    {
        $validate = Validator::make($request->all(), $user->emailLoginRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the user by email
        $user = User::where('email', $request->email)->first();

        // Check if the user exists and the password is correct
        if ($user && Hash::check($request->password, $user->password)) {
            $accessToken = $jwtService->generateToken($user, 'access');
            $refreshToken = $jwtService->generateToken($user, 'refresh');

            $data = [
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
            return $this->sendResponse($data, 'successfully');
        } else {
            return $this->sendError('Invalid Credentials');
        }
    }

    public function userDetail(Request $request, UserResource $userResource)
    {
        $user = $request->get('user');

        if (!($user)) {
            return $this->sendError('Account unauthenticated');
        }


        $account_data = $userResource->userDetail($user);
        return $this->sendResponse($account_data, 'successfully');
    }

    public function accessTokenRegenerate(Request $request, JwtService $jwtService)
    {
        $data = $request->all();
        $refresh_token = $data['refresh_token'];

        $validated_data = $jwtService->validateToken($refresh_token);

        if ($validated_data['error']) {
            return $this->sendError($validated_data['error']);
        } elseif ($validated_data['tokenType'] != 'refresh'){
            return $this->sendError('Invalid token.');
        }

        $new_access_token = $jwtService->generateToken($validated_data['user'], 'access');

        $data = [
            'accessToken' => $new_access_token
        ];

        return $this->sendResponse($data, 'successfully');
    }
}
