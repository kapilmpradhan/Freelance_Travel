<?php

namespace App\Services;

use App\Models\Otp;
use Carbon\Carbon;

class OtpService
{
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
                return ['success'=>false, 'error'=>'New otp can be generated after ' . $timeDifferenceInSeconds . 'seconds.'];
            }

            $otp->otp = $otpValue;
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
}
