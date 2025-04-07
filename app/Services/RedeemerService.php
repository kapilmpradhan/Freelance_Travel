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

    public static function addOrUpdatePrimaryRedeemer($user)
    {
        $primaryRedeemer = CartCustomerDetail::updateOrCreate([
            'user_id' => $user->uuid,
            'is_primary' => true,
        ], [
            'title' => $user->title,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'date_of_birth' => $user->date_of_birth,
            'email' => $user->email,
            'postal_code' => $user->postal_code,
            'phone_number' => $user->phone_number
        ]);
        return ServiceResponse::success($primaryRedeemer);
    }

    public static function listActiveRedeemers($userId)
    {
        $redeemers = CartCustomerDetail::where('user_id', $userId)
                        ->where('is_deleted', false)
                        ->get();

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

        $redeemer->is_deleted = true;
        $redeemer->save();
        return ServiceResponse::success();
    }
}
