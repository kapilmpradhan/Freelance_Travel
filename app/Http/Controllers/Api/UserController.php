<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
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

        return $this->sendResponse('successfully', $return_data);
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
            return $this->sendResponse('JWT tokens', $data);
        } else {
            return $this->sendError('Invalid Credentials');
        }
    }

    public function changePassword(Request $request, User $user)
    {
        $data = $request->all();
        $validate = Validator::make($data, $user->changePasswordRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the user by email
        $user = User::where('email', $request->user->email)->first();

        if (!$user) {
            return $this->sendError('User not found');
        } elseif ($user->sso_type != 'email') {
            return $this->sendError('Email signed up user can only change password.');
        } elseif (!Hash::check($data['current_password'], $user->password)) {
            return $this->sendError('Incorrect current password');
        } else {
            $user->password = Hash::make($data['new_password']);
            $user->save();
            return $this->sendResponse('Password changed');
        }
    }

    public function updatePassword(Request $request, User $user)
    {
        $data = $request->all();
        $validate = Validator::make($data, $user->resetPasswordRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $user = $user->getSsoEmailUser($data['email']);

        if (!$user) {
            return $this->sendError('Invalid OTP/email');
        }

        $is_otp_valid = OtpService::checkOtpForPasswordUpdate($user, $data['otp']);

        if ($is_otp_valid['success'] == false) {
            return $this->sendError($is_otp_valid['error']);
        }

        try {
            $user->updatePassword($data['new_password']);
            return $this->sendResponse('Password changed successfully');
        } catch (\Exception $e) {
            return $this->sendError('Password reset failed');
        }
    }

    public function userDetail(Request $request, UserResource $userResource)
    {
        $user = $request->get('user');

        if (!($user)) {
            return $this->sendError('Account unauthenticated');
        }


        $account_data = $userResource->userDetail($user);
        return $this->sendResponse('Account details', $account_data);
    }

    public function accessTokenRegenerate(Request $request, JwtService $jwtService)
    {
        $data = $request->all();
        $refresh_token = $data['refresh_token'];

        $validated_data = $jwtService->validateToken($refresh_token);

        if ($validated_data['error']) {
            return $this->sendError($validated_data['error']);
        } elseif ($validated_data['tokenType'] != 'refresh') {
            return $this->sendError('Invalid token.');
        }

        $new_access_token = $jwtService->generateToken($validated_data['user'], 'access');

        $data = [
            'accessToken' => $new_access_token
        ];

        return $this->sendResponse('New access token', $data);
    }
}
