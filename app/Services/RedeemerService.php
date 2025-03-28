<?php

namespace App\Services;

use App\Models\CartCustomerDetail;

class RedeemerService
{
    public static function addNewRedeemer($userId, $redeemerData)
    {
        $redeemer = CartCustomerDetail::create([
            'user_id' => $userId,
            'title' => $redeemerData['title'],
            'first_name' => $redeemerData['firstName'],
            'last_name' => $redeemerData['lastName'],
            'date_of_birth' => $redeemerData['dateOfBirth'],
            'email' => $redeemerData['email'],
            'postal_code' => $redeemerData['postalCode'],
            'phone_number' => $redeemerData['phoneNumber']
        ]);

        return ServiceResponse::success($redeemer);
    }

    public static function listRedeemers($userId)
    {
        $redeemers = CartCustomerDetail::where('user_id', $userId)->get();

        return ServiceResponse::success($redeemers);
    }

    public static function removeRedeemer($userId, $redeemerId)
    {
        $redeemer = CartCustomerDetail::where('user_id', $userId)
                                    ->where('id', $redeemerId)
                                    ->first();
        if (!$redeemer) {
            return ServiceResponse::notFound(message: 'Redeemer not found');
        };

        $redeemer->delete();
        return ServiceResponse::success();
    }
}
