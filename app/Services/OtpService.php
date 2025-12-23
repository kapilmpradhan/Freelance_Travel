<?php

namespace App\Services;

use App\Logging\Logger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Otp;
use App\Models\User;

class OtpService
{
    public static function checkOtpForPasswordUpdate(User $user, string $otp)
    {
        $availableOtp = Otp::where('user_id', $user->uuid)
                            ->first();

        if (
            $availableOtp &&
            Carbon::now()->diffInSeconds($availableOtp->expire_timestamp) > 0 &&
            $availableOtp->is_verified &&
            $availableOtp->otp == $otp
        ) {
            $availableOtp->delete();

            Logger::debug('OTP verified for password update', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_password_update_verified',
            ]);

            return ['success' => true];
        }

        Logger::debug('OTP verification failed for password update', [
            'log_file' => config('logging.log_files.user_otp'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'otp_password_update_failed',
        ]);

        return ['success' => false, 'error' => 'Unable to update password'];
    }

    public static function generateOtp(User $user)
    {
        // Generate OTP
        $otpValue = rand(10000, 99999);

        $otp = Otp::where('user_id', $user->uuid)
                    ->first();

        if ($otp) {
            // New otp can be generated after 60 seconds
            $timeDifferenceInSeconds = Carbon::now()->diffInSeconds($otp->created_timestamp);
            if ($timeDifferenceInSeconds < 60) {
                Logger::debug('OTP generation throttled', [
                    'log_file' => config('logging.log_files.user_otp'),
                    'user_id' => $user->uuid,
                    'user_email' => $user->email,
                    'action' => 'otp_generation_throttled',
                    'wait_seconds' => 60 - $timeDifferenceInSeconds,
                ]);

                return [
                    'success' => false,
                    'error' => 'New otp can be generated after ' . 60 - $timeDifferenceInSeconds . 'seconds.'];
            }

            $otp->otp = $otpValue;
            $otp->created_timestamp = Carbon::now();
            $otp->expire_timestamp = Carbon::now()->addMinutes(5);
            $otp->is_verified = false;
            $otp->save();

            Logger::debug('OTP regenerated for user', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_regenerated',
            ]);

            return ['success' => true, 'otpDetails' => $otp];
        }

        try {
            $newOtp = Otp::create(['user_id' => $user->uuid,'otp' => $otpValue]);

            Logger::debug('OTP generated for user', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_generated',
            ]);

            return ['success' => true, 'otpDetails' => $newOtp];
        } catch (\Exception $e) {
            Logger::error(
                "Generate OTP exception",
                $e,
                null,
                [
                    'log_file' => config('logging.log_files.errors'),
                    'user_id' => $user->uuid,
                    'user_email' => $user->email,
                    'action' => 'otp_generation_failed',
                ]
            );
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function verifyOtp(User $user, string $otp)
    {
        $otpRecord = Otp::where('user_id', $user->uuid)
                            ->where('otp', $otp)
                            ->first();
        if (!$otpRecord or $otpRecord->is_verified) {
            Logger::debug('OTP verification failed - invalid OTP', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_verify_invalid',
            ]);

            return ['success' => false, 'error' => 'Invalid OTP'];
        } elseif (Carbon::now()->diffInSeconds($otpRecord->expire_timestamp) < 0) {
            Logger::debug('OTP verification failed - expired OTP', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_verify_expired',
            ]);

            return ['success' => false, 'error' => 'Expired OTP'];
        } else {
            DB::transaction(function () use ($otpRecord, $user) {
                $otpRecord->is_verified = true;
                $otpRecord->save();

                $user->is_email_verified = true;
                if ($user->profile_status === 'require_real_email') {
                    $user->profile_status = 'success';
                }
                $user->save();
            });

            Logger::debug('OTP verified successfully', [
                'log_file' => config('logging.log_files.user_otp'),
                'user_id' => $user->uuid,
                'user_email' => $user->email,
                'action' => 'otp_verified',
            ]);

            return ['success' => true];
        }
    }
}
