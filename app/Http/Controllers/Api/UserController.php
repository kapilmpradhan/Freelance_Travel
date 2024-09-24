<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;

class UserController extends BaseController
{
    public function userSignupEmail(Request $request, User $user, UserResource $userResource)
    {
        $data = $request->all(); // Retrive request data
        $data['sso_type'] = 'email'; // Add email sso to the array

        $validate = Validator::make($data, $user->signupRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $new_user = $user->storeUser($data);

        $return_data = $userResource->userDetail($new_user);

        return $this->sendResponse($return_data, 'successfully');
    }
}
