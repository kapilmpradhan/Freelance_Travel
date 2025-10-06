<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\Models\CartItem;
use App\Models\Quote;
use App\Models\UserOrderCommission;

class UserOrderCommissionService
{
    public static function getUserOrderCommissionByItemType($userId, ItemType $itemType): ServiceResponse
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
                                    ->where('agent_branch', app('agentType')->agent->branch_code)
                                    ->first();

        if ($userOrderCommission) {
            return ServiceResponse::success($userOrderCommission);
        }

        return ServiceResponse::notFound();
    }

    public static function getOrSetCommissionOfUserCartOrQuote($userId, ItemType $itemType): ServiceResponse
    {
        $commissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
            $userId,
            $itemType
        );
        $commission = $commissionResponse->data;
        if ($commissionResponse->isSuccess() && $commission->percentage && $commission->points_available) {
            $commissionPercentage = $commissionResponse->data->percentage;
            $pointsAvailable = $commissionResponse->data->points_available;

            return ServiceResponse::success([
                'commission' => $commissionPercentage,
                'pointsAvailable' => $pointsAvailable,
            ]);
        }

        $items = CartItem::userItems($userId, $itemType);
        if (count($items) == 0) {
            return ServiceResponse::success(['commission' => 0]);
        }
        $itemType->data = $items->toArray();

        $orderCommissionResponse = BookingService::postOrder(
            userId: $userId,
            intent: 'pay-now',
            pointsApplied: null,
            itemType: $itemType
        );

        if ($orderCommissionResponse->isError()) {
            return ServiceResponse::success([
                'commission' => 0,
                'pointsAvailable' => 0
            ]);
        }

        $commissionData = $orderCommissionResponse->data;
        $pointsAvailable = $commissionData['pointsAvailable'];

        UserOrderCommission::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'user_order_id' => null,
                'agent_branch' => $commissionData['branch'],
                'is_cart' => $itemType->isCart,
                'quote_id' => $itemType->typeId,
                'is_direct_purchase' => $itemType->isDirect
            ],
            [
                'percentage' => $commissionData['commission'],
                'points_available' => $pointsAvailable,
            ]
        );

        return ServiceResponse::success([
            'commission' => $commissionData['commission'],
            'pointsAvailable' => $pointsAvailable,
        ]);
    }

    public static function getCommissionForDry($userId, ItemType $itemType): ServiceResponse
    {
        $overallCartData = [];
        foreach ($itemType->data as $data) {
            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $userId,
                tdmsProductId: $data['tdmsProductId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                itemType: $itemType,
                productPricesDetails: $data['productPricesDetails']
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
            pointsApplied: null,
            itemType: $itemType
        );

        if ($orderCommissionResponse->isError()) {
            return ServiceResponse::success(['commission' => 0]);
        }

        $commissionData = $orderCommissionResponse->data;

        return ServiceResponse::success([
            'commission' => $commissionData['commission'],
            'pointsAvailable' => $commissionData['pointsAvailable'],
        ]);
    }

    public static function pointsFromCommission(float $commission, int $pointsMultiplier): int
    {
        return (float) round($commission * $pointsMultiplier, 2);
    }

    public static function pointsToDollars(?float $points, int $pointsMultiplier): ?float
    {
        if (!$points) {
            return null;
        }

        return round($points / $pointsMultiplier, 2);
    }
}
