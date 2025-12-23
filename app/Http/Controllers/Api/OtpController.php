<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\SendForgotPasswordOtp;
use App\Services\OtpService;
use App\Logging\Logger;

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
            Logger::debug('Send OTP failed - email not registered', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_email' => $data['email'],
                'action' => 'send_otp_email_not_found',
                'platform' => app('platform'),
            ]);

            return $this->sendError('Email not registered');
        }

        if ($user->sso_type !== 'email') {
            Logger::debug('Send OTP failed - SSO user', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'send_otp_sso_user',
                'platform' => app('platform'),
            ]);

            return $this->sendError('SSO registered email cannot perform this action.');
        }

        // Generate OTP
        $generatedOtp = OtpService::generateOtp($user);
        if ($generatedOtp['success'] == false) {
            return $this->sendError($generatedOtp['error']);
        }

        $otpDetails = $generatedOtp['otpDetails'];

        // Dispatch the job to send the email
        SendForgotPasswordOtp::dispatch($user, $otpDetails->otp, app('platform'));

        Logger::debug('OTP sent for forgot password', [
            'log_file' => config('logging.log_files.user_otp'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'send_otp_forgot_password',
            'platform' => app('platform'),
        ]);

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
            Logger::debug('Verify OTP failed - user not found', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_email' => $data['email'],
                'action' => 'verify_otp_user_not_found',
                'platform' => app('platform'),
            ]);

            return $this->sendError('Invalid OTP/email');
        }

        $is_otp_verfied = OtpService::verifyOtp($user, $data['otp']);

        if ($is_otp_verfied['success'] == false) {
            return $this->sendError($is_otp_verfied['error']);
        }

        Logger::debug('OTP verified successfully via controller', [
            'log_file' => config('logging.log_files.user_otp'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'verify_otp_success',
            'platform' => app('platform'),
        ]);

        return $this->sendResponse('Otp Verfied');
    }
}
