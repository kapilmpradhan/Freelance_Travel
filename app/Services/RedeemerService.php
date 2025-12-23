<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\Logging\Logger;
use App\Models\CartCustomerDetail;
use App\Models\Quote;

class RedeemerService
{
    public static function addNewRedeemer($userId, $redeemerData, ItemType $itemType)
    {
        $userRedeemers = CartCustomerDetail::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($itemType->isSession, fn ($query) => $query->where('session_id', $itemType->typeId))
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

        if ($sameNameRedeemers->count() > 0) {
            $redeemerData['lastName'] .= $sameNameRedeemers->count();
        }

        $redeemer = CartCustomerDetail::create([
            'user_id' => $userId,
            'session_id' => $itemType->isSession ? $itemType->typeId : null,
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
            'country_code' => $user->country_code,
            'phone_number' => $user->phone_number
        ]);

        Logger::debug('Primary redeemer updated', [
            'log_file' => config('logging.log_files.user_profile'),
            'user_id' => $user->uuid,
            'user_email' => $user->email,
            'action' => 'primary_redeemer_updated',
        ]);

        return ServiceResponse::success($primaryRedeemer);
    }

    public static function listActiveRedeemers(string|null $userId, ItemType $itemType)
    {
        $query = CartCustomerDetail::where('user_order_id', null)
            ->where('is_deleted', false);

        if ($itemType->isSession) {
            $redeemers = (clone $query)->where('session_id', $itemType->typeId)->get();
            return ServiceResponse::success($redeemers);
        }

        $primaryRedeemer = (clone $query)->where('is_primary', true)
            ->where('user_id', $userId)
            ->first();

        if (!$itemType->isQuote) {
            $query = $query->where('user_id', $userId);
        }

        if ($itemType->isQuote) {
            $query->where('quote_id', $itemType->typeId);
        } elseif ($itemType->isDirect) {
            $query->where('is_direct_purchase', true);
        } else {
            $query->where('quote_id', null)
                ->where('is_direct_purchase', false);
        }

        $redeemers = $query->where('is_primary', false)->get();

        if ($itemType->isQuote) {
            $quote = Quote::where('id', $itemType->typeId)->first();
            if (!$quote) {
                return ServiceResponse::notFound();
            }

            if ($quote->user_id != $userId) {
                $primaryRedeemerOfQuote = CartCustomerDetail::where('user_id', $quote->user_id)
                    ->where('is_primary', true)
                    ->first();

                if ($primaryRedeemerOfQuote) {
                    $redeemers->prepend($primaryRedeemerOfQuote);
                }
            }
        }


        if ($primaryRedeemer) {
            $redeemers->prepend($primaryRedeemer);
        }

        return ServiceResponse::success($redeemers);
    }

    public static function removeRedeemer(string|null $userId, string|null $sessionId, $redeemerId)
    {
        $redeemer = CartCustomerDetail::query()
                                    ->when($userId, fn ($query) => $query->where('user_id', $userId))
                                    ->when($sessionId, fn ($query) => $query->where('session_id', $sessionId))
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
