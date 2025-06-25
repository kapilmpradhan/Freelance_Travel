<?php

namespace App\Services;

use App\Models\UserOrderCommission;

class UserOrderCommissionService
{
    public static function getUserOrderCommissionByItemType($userId, $itemType)
    {
        $userOrderCommission = UserOrderCommission::where('user_id', $userId)
                                    ->where('is_cart', $itemType->isCart)
                                    ->where('quote_id', $itemType->typeId)
                                    ->where('is_direct_purchase', $itemType->isDirect)
                                    ->where('user_order_id', null)
                                    ->first();

        if ($userOrderCommission) {
            return ServiceResponse::success($userOrderCommission);
        }

        return ServiceResponse::notFound();
    }
}
