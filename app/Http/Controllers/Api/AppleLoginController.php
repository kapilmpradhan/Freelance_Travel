<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\AppleService;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Support\Facades\Validator;
use App\Services\OtpService;
use App\Jobs\SendProfileEmailOtp;
use App\Jobs\UserProfileAgentJob;

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

        $user = User::where('email', $email)->first();
        $requires_real_email = str_ends_with($email, '@privaterelay.appleid.com');

        if (!$user) {
            $user = User::Create([
                    'email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'sso_type' => 'apple',
                    'is_email_verfied' => true,
                    'profile_status' => $requires_real_email ? 'require_real_email' : 'in_progress'
                ]);
        }
        $data = [
            "accessToken" => $jwtService->generateToken($user, 'access'),
            "refreshToken" => $jwtService->generateToken($user, 'refresh')
        ];

        return $this->sendResponse('successfully', $data);
    }

    public function getRealEmailOTP(Request $request)
    {
        $user = $request->user;
        $data = $request->all();

        $validate = Validator::make($data, ["new_email" => "required|email"]);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        $existingEmail = User::where('email', $data['new_email'])->first();
        if ($existingEmail) {
            return $this->sendError('User with email already exists');
        }

        if ($user->profile_status !== 'require_real_email') {
            return $this->sendError('User profile status is ' . $user->profile_status);
        }

        $otp = OtpService::generateOtp($user);
        if ($otp['success'] == false) {
            return $this->sendError($otp['error']);
        }
        SendProfileEmailOtp::dispatch($data['new_email'], $otp['otpDetails']->otp);

        return $this->sendResponse('OTP sent to ' . $data['new_email']);
    }

    public function verifyRealEmailOTP(Request $request)
    {
        $user = $request->user;
        $data = $request->all();

        $validate = Validator::make($data, ["otp" => "string|required", "new_email" => "email|required"]);
        if ($validate->fails()) {
            return $this->sendError('Error occured', $validate->errors(), 400);
        }

        $otp = OtpService::verifyOtp($user, $data['otp']);
        if ($otp['success'] == false) {
            return $this->sendError($otp['error']);
        }

        $user->profile_status = 'in_progress';
        $user->save();

        $user->email = $data['new_email'];
        UserProfileAgentJob::dispatch($user);

        return $this->sendResponse('Private apple account connected to real email account.');
    }
}
