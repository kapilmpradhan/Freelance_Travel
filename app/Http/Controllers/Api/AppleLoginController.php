<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\AppleService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Validator;

class AppleLoginController extends BaseController
{
    public function appleAuthCallback(Request $request)
    {
        $body = http_build_query($request->all());
        $redirectUrl = "intent://callback?{$body}#Intent;" .
               "package=" . Config::get('services.apple.client_id') . ";" .
               "scheme=signinwithapple;end";
        return redirect()->away($redirectUrl);
    }

    public function userLoginApple(Request $request, JwtService $jwtService)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'access_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Invalid Input', $validator->errors());
        }

        $appleUser = AppleService::getUserInfo($data);
        if (!$appleUser['success']) {
            return response()->json(['error' => $appleUser['errors']], 400);
        }

        $email = $appleUser['data']['email'];
        $first_name = $appleUser['data']['first_name'];
        $last_name = $appleUser['data']['last_name'];

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'sso_type' => 'apple',
                'is_email_verfied' => true
            ]
        );

        $data = [
            "accessToken" => $jwtService->generateToken($user, 'access'),
            "refreshToken" => $jwtService->generateToken($user, 'refresh')
        ];

        return $this->sendResponse($data, 'successfully');
    }
}
