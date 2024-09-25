<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\JwtService;
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

        return $this->sendResponse($return_data, 'successfully');
    }

    public function userLoginEmail(Request $request, User $user, JwtService $jwtService)
    {
        $validate = Validator::make($request->all(), $user->emailLoginRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the account by email
        $account = User::where('email', $request->email)->first();

        // Check if the account exists and the password is correct
        if ($account && Hash::check($request->password, $account->password)) {
            $accessToken = $jwtService->generateToken($account, 'access');
            $refreshToken = $jwtService->generateToken($account, 'refresh');

            $data = [
                'accessToken' => $accessToken,
                'refreshToken' => $refreshToken
            ];
            return $this->sendResponse($data, 'successfully');
        } else {
            return $this->sendError('Invalid Credentials');
        }
    }
}
