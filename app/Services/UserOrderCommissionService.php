<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\UserOrderCommission;

class UserOrderCommissionService
{
    public static function getUserOrderCommissionByItemType($userId, $itemType)
    {
        $userOrderCommission = UserOrderCommission::where('user_id', $userId)
                                    ->where('is_cart', $itemType->isCart)
                                    ->when(
                                        $itemType->isQuote,
                                        fn ($query)
                                        => $query->where('quote_id', $itemType->typeId)
                                    )
                                    ->where('is_direct_purchase', $itemType->isDirect)
                                    ->where('user_order_id', null)
                                    ->first();

        if ($userOrderCommission) {
            return ServiceResponse::success($userOrderCommission);
        }

        return ServiceResponse::notFound();
    }

    public static function getOrSetCommissionOfUserCartOrQuote($userId, $itemType)
    {
        $commissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
            $userId,
            $itemType
        );
        $commission = $commissionResponse->data;
        if ($commissionResponse->isSuccess() && $commission->percentage) {
            $commissionPercentage = $commissionResponse->data->percentage;
            return ServiceResponse::success(['commission' => $commissionPercentage]);
        }

        $items = CartItem::userItems($userId, $itemType);
        if (count($items) == 0) {
            return ServiceResponse::success(['commission' => 0]);
        }
        $itemType->data = $items->toArray();

        $orderCommissionResponse = BookingService::postOrder(
            userId: $userId,
            intent: 'pay-now',
            processAsQuote: true,
            itemType: $itemType
        );

        if ($orderCommissionResponse->isError()) {
            return ServiceResponse::success(['commission' => 0]);
        }

        $commissionData = $orderCommissionResponse->data;

        UserOrderCommission::updateOrCreate(
            [
                'user_id' => $userId,
                'user_order_id' => null,
                'agent_branch' => $commissionData['branch'],
                'is_cart' => $itemType->isCart,
                'quote_id' => $itemType->typeId,
                'is_direct_purchase' => $itemType->isDirect
            ],
            [
                'percentage' => $commissionData['commission']
            ]
        );

        return ServiceResponse::success(['commission' => $commissionData['commission']]);
    }

    public static function getCommissionForDry($userId, $itemType)
    {
        $overallCartData = [];
        foreach ($itemType->data as $data) {
            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $userId,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetails: $data['productPricesDetails'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                addToQuote: null,
                itemType: $itemType
            );

            if (!$saveItemsResponse->isSuccess()) {
                return ServiceResponse::success(['commission' => 0]);
            }
            $overallCartData = array_merge($overallCartData, $saveItemsResponse->data);
        }

        $itemType->data = $overallCartData;
        $orderCommissionResponse = BookingService::postOrder(
            userId: $userId,
            intent: 'pay-now',
            processAsQuote: true,
            itemType: $itemType
        );

        if ($orderCommissionResponse->isError()) {
            return ServiceResponse::success(['commission' => 0]);
        }

        $commissionData = $orderCommissionResponse->data;

        return ServiceResponse::success(['commission' => $commissionData['commission']]);
    }
}
