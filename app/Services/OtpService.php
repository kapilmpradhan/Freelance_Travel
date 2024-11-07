<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Otp;


class OtpService
{

    public static function checkOtpForPasswordUpdate($user, $otp)
    {
        $availableOtp = Otp::where('user_id', $user->uuid)
                            ->first();

        if ($availableOtp && Carbon::now()->diffInSeconds($availableOtp->expire_timestamp) > 0 && $availableOtp->is_verified && $availableOtp->otp==$otp)
        {
            $availableOtp->delete();
            return ['success'=>true];
        }

        return ['success'=>false, 'error'=>'Unable to update password'];
    }
    
    public static function generateOtp($user)
    {
        // Generate OTP
        $otpValue = rand(10000, 99999);

        $otp = Otp::where('user_id', $user->uuid)
                    ->first();
        
        if ($otp) {
            // New otp can be generated after 60 seconds
            $timeDifferenceInSeconds = Carbon::now()->diffInSeconds($otp->created_timestamp);
            if ( $timeDifferenceInSeconds < 60){
                return ['success'=>false, 'error'=>'New otp can be generated after ' . 60 - $timeDifferenceInSeconds . 'seconds.'];
            }

            $otp->otp = $otpValue;
            $otp->created_timestamp = Carbon::now();
            $otp->expire_timestamp = Carbon::now()->addMinutes(5);
            $otp->is_verified = false;
            $otp->save();

            return ['success'=>true, 'otpDetails'=>$otp];
        }

        try{
            $newOtp = Otp::create(['user_id' => $user->uuid,'otp' => $otpValue]);
            return ['success'=>true, 'otpDetails'=> $newOtp];
        } catch (\Exception $e) {
            return ['success'=>false, 'error'=>$e->getMessage()];
        }
    }

    public static function verifyOtp($user, $otp)
    {
        $otp = Otp::where('user_id', $user->uuid)
                            ->where('otp', $otp)
                            ->first();
        if (!$otp)
        {
            return ['success'=>false, 'error'=>'Invalid OTP'];
        }
        elseif ($otp && Carbon::now()->diffInSeconds($otp->expire_timestamp) < 0 && $otp->is_verified==true)
        {
            return ['success'=>false, 'error'=>'Expired OTP'];
        }
        else
        {
            $otp->is_verified = true;
            $otp->save();

            return ['success'=>true];
        }

    }
}
