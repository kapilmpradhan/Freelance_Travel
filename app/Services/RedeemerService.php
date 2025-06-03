<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\Models\CartCustomerDetail;

class RedeemerService
{
    public static function addNewRedeemer($userId, $redeemerData, ItemType $itemType)
    {
        $userRedeemers = CartCustomerDetail::where('user_id', $userId)
            ->where('is_deleted', false)
            ->where(function ($query) use ($itemType) {
                if ($itemType->isQuote) {
                    $query->where('quote_id', $itemType->typeId);
                } elseif ($itemType->isDirect) {
                    $query->where('is_direct_purchase', true);
                } else {
                    $query->where('quote_id', null)
                        ->where('is_direct_purchase', false);
                }
            })
            ->where('user_order_id', null);

        $sameNameRedeemers = (clone $userRedeemers)
            ->where('first_name', $redeemerData['firstName'])
            ->where('last_name', 'like', $redeemerData['lastName'] . '%');

        $sameEmailRedeemers = (clone $userRedeemers)
            ->where('email', $redeemerData['email']);

        if ($sameEmailRedeemers->count() > 0) {
            return ServiceResponse::badRequest(
                message: 'A redeemer with this email already exists.'
            );
        }

        if ($sameNameRedeemers->count() > 0) {
            $redeemerData['lastName'] = $sameNameRedeemers->
                                        orderByDesc('created_at')
                                        ->first()
                                        ->last_name . '+';
        }

        $redeemer = CartCustomerDetail::create([
            'user_id' => $userId,
            'title' => $redeemerData['title'],
            'first_name' => $redeemerData['firstName'],
            'last_name' => $redeemerData['lastName'],
            'date_of_birth' => $redeemerData['dateOfBirth'],
            'email' => $redeemerData['email'],
            'postal_code' => $redeemerData['postalCode'],
            'phone_number' => $redeemerData['phoneNumber'],
            'quote_id' => $itemType->isQuote ? $itemType->typeId : null,
            'is_direct_purchase' => $itemType->isDirect,
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

    public static function listActiveRedeemers($userId, ItemType $itemType)
    {
        $query = CartCustomerDetail::where('user_id', $userId)
            ->where('user_order_id', null)
            ->where('is_deleted', false);

        $primaryRedeemer = (clone $query)->where('is_primary', true)->first();

        if ($itemType->isQuote) {
            $query->where('quote_id', $itemType->typeId);
        } elseif ($itemType->isDirect) {
            $query->where('is_direct_purchase', true);
        } else {
            $query->where('quote_id', null)
                ->where('is_direct_purchase', false);
        }

        $redeemers = $query->where('is_primary', false)->get();
        if ($primaryRedeemer) {
            $redeemers->prepend($primaryRedeemer);
        }

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
