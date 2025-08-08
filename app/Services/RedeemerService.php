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
            ->where('user_order_id', null)
            ->orWhere(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('is_primary', true);
            })
            ->get();

        // Filter for same name redeemers
        $sameNameRedeemers = $userRedeemers->filter(function ($redeemer) use ($redeemerData) {
            return $redeemer->first_name === $redeemerData['firstName'] &&
                preg_match('/^' . preg_quote($redeemerData['lastName'], '/') . '([0-9]*)?$/', $redeemer->last_name);
        });

        // Filter for same email redeemers
        // $sameEmailRedeemers = $userRedeemers->filter(function ($redeemer) use ($redeemerData) {
        //     return $redeemer->email === $redeemerData['email'];
        // });

        // if ($sameEmailRedeemers->count() > 0) {
        //     return ServiceResponse::badRequest(
        //         message: 'A redeemer with this email already exists.'
        //     );
        // }

        if ($sameNameRedeemers->count() > 0) {
            $redeemerData['lastName'] .= $sameNameRedeemers->count();
        }

        $redeemer = CartCustomerDetail::create([
            'user_id' => $userId,
            'title' => $redeemerData['title'],
            'first_name' => $redeemerData['firstName'],
            'last_name' => $redeemerData['lastName'],
            'date_of_birth' => $redeemerData['dateOfBirth'],
            'email' => $redeemerData['email'],
            'country_code' => $redeemerData['countryCode'],
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
            'postal_code' => $user->post_code,
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
