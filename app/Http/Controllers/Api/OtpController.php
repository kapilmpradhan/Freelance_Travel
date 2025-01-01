<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendForgotPasswordOtp;
use App\Jobs\UserProfileAgentJob;
use App\Models\Otp;
use App\Services\OtpService;

class OtpController extends BaseController
{
    public function sendOtp(Request $request, User $user)
    {
        $data = $request->all();
        $validate = Validator::make($data, $user->forgotPasswordRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        // Retrieve the user by email
        $user = $user->getSsoEmailUser($data['email']);

        if (!$user) {
            return $this->sendError('Invalid email');
        }

        // Generate OTP
        $generatedOtp = OtpService::generateOtp($user);
        if ($generatedOtp['success'] == false) {
            return $this->sendError($generatedOtp['error']);
        }

        $otpDetails = $generatedOtp['otpDetails'];

        // Dispatch the job to send the email
        SendForgotPasswordOtp::dispatch($user->email, $otpDetails->otp);

        return $this->sendResponse('OTP sent to your email');
    }

    public function verifyOtp(Request $request, User $user)
    {
        $data = $request->all();

        $validate = Validator::make($data, $user->verifyOtpRule());
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return $this->sendError('Invalid OTP/email');
        }

        $is_otp_verfied = OtpService::verifyOtp($user, $data['otp']);

        if ($is_otp_verfied['success'] == false) {
            return $this->sendError($is_otp_verfied['error']);
        }
        $updated_user = User::findOrFail($user->uuid);
        if ($updated_user->profile_status == 'in_progress') {
            UserProfileAgentJob::dispatch($updated_user);
        }

        return $this->sendResponse('Otp Verfied');
    }
}
