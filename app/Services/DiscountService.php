<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Discount;
use App\Models\UserAgent;
use App\Models\UserOrderCommission;
use Exception;
use Laravel\Pennant\Feature;

class DiscountService
{
    public static function getActiveDiscountData($user = null)
    {
        if ($user && Feature::for($user)->active('tester')) {
            $activeDiscount = Discount::where('is_test', true)->first();
        } else {
            $activeDiscount = Discount::where('is_active', true)->first();
        }
        if (!$activeDiscount) {
            $data = [
                'title' => null,
                'description' => null,
                'discount' => 0
            ];
        } else {
            $data = [
                'title' => $activeDiscount->title,
                'description' => $activeDiscount->description,
                'discount' => $activeDiscount->percentage
            ];
        }

        return ServiceResponse::success(
            data: $data
        );
    }

    public static function getOrderCommission(
        ItemType $itemType,
        $userId,
        ?int $pointsApplied = null
    ): ServiceResponse {
        $items = CartItem::userItems($userId, $itemType);
        if (count($items) == 0) {
            return ServiceResponse::notFound('No items available');
        }

        $orderCommissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType($userId, $itemType);
        $orderCommission = $orderCommissionResponse->data; /** @var UserOrderCommission $orderCommission */

        if (!$orderCommission || is_null($orderCommission->percentage)) {
            $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);
            if ($getAgentResponse->isError()) {
                return $getAgentResponse;
            }
            $agent = $getAgentResponse->data; /** @var UserAgent $agent */

            if (!$orderCommission) {
                $orderCommission = UserOrderCommission::create([
                    'user_id' => $userId,
                    'agent_branch' => $agent['branch_code'],
                    'is_cart' => $itemType->isCart,
                    'quote_id' => $itemType->typeId,
                    'is_direct_purchase' => $itemType->isDirect,
                    'percentage' => null
                ]);
            }

            $items = CartItem::userItems(userId: $userId, itemType: $itemType);

            $bookingReference = TdmsService::getBookingRefrence($agent->access_token);
            $onlinePaymentMethod = BookingService::getOnlinePaymentMethod($agent->access_token);
            if (is_null($onlinePaymentMethod)) {
                throw new ServiceException('Missing online payment method');
            }

            // This is a dry run to calculate the commission percentage
            $itemType->forDiscount = true;
            $orderRequestData = BookingService::buildOrderRequestData(
                userAgent: $agent,
                userId: $userId,
                processAsQuote: true,
                bookingReference: $bookingReference,
                paymentMethodCode: $onlinePaymentMethod['code'],
                pointsApplied: $pointsApplied,
                cartItems: $items,
                customers: [],
                forDiscount: $itemType->forDiscount
            );

            $validateOrderDataResponse = TdmsService::validateOrderData(
                agentToken: $agent->access_token,
                bookingReference: $bookingReference,
                orderData: $orderRequestData,
            );

            if ($validateOrderDataResponse->isError()) {
                return ServiceResponse::badRequest(
                    message: 'Failed to validate order data',
                    data: $validateOrderDataResponse->data
                );
            }

            $data = $validateOrderDataResponse->data;
            $commission = $data['commission']['message']['estimatedCommission'];
            $totalRrp = $orderRequestData['totalCharged'];

            $commissionPercentage = round($commission / $totalRrp * 100, 2);

            $orderCommission->percentage = $commissionPercentage;
            $orderCommission->points_available = UserOrderCommissionService::pointsFromCommission(
                (float) $commission
            );
            $orderCommission->save();
        }
        return ServiceResponse::success($orderCommission);
    }

    // Calculates the overall discount based on the active discount percentage and commission percentage
    // e.g. If active discount is 10% and commission is 15%, the overall discount will be 10%
    // e.g. If active discount is 10% and commission is 12%, the overall discount will be 7% (12% - 5% threshold)
    // Reference link: blob:https://websitetravel.atlassian.net/854bd471-24c8-48b2-a606-745b19d8fa2e#media-blob-url=true&id=c0b28879-aa4e-4615-8fc6-7ec45200a9dd&collection=&contextId=22708&width=855&height=335&alt=
    public static function calcuateOverallDiscount($commissionPercentage, $threshold = 5, $user = null)
    {
        if (Feature::for($user)->active('tester')) {
            $activeDiscount = Discount::where('is_test', true)->first();
        } else {
            $activeDiscount = Discount::where('is_active', true)->first();
        }

        if (!$activeDiscount || $activeDiscount->percentage == 0) {
            return 0;
        }

        $activeDiscountPercentage = $activeDiscount->percentage;
        if ($commissionPercentage - $threshold >= $activeDiscountPercentage) {
            $applicableDiscount = $activeDiscountPercentage;
        } elseif (
            $commissionPercentage - $threshold < $activeDiscountPercentage &&
            $commissionPercentage - $threshold > 0
        ) {
            $applicableDiscount = $commissionPercentage - $threshold;
        } else {
            $applicableDiscount = 0;
        }

        return $applicableDiscount;
    }

    public static function getItemsDiscount(
        ItemType $itemType,
        $userId,
        ?int $pointsApplied = null
    ): ServiceResponse {
        $activeDiscount = Discount::where('is_active', true)->first();
        if (!$activeDiscount || $activeDiscount->percentage == 0) {
            return ServiceResponse::success(
                data: ['applicableDiscount' => 0]
            );
        }

        $orderCommissionResponse = self::getOrderCommission($itemType, $userId, $pointsApplied);
        if ($orderCommissionResponse->isError()) {
            return $orderCommissionResponse;
        }

        $agentBranch = $orderCommissionResponse->data->agent_branch;

        // TODO: Hard-coded branch code to FTX for now. Will update later!!
        if ($agentBranch !== 'FTX') {
            return ServiceResponse::success(data: ['discount' => 0]);
        }

        $applicableDiscount = self::calcuateOverallDiscount(
            commissionPercentage: $orderCommissionResponse->data->percentage,
        );

        return ServiceResponse::success(
            data: ['applicableDiscount' => $applicableDiscount]
        );
    }

    public static function getActiveDiscount()
    {
        $activeDiscount = Discount::where('is_active', true)->first();
        if (!$activeDiscount) {
            return ServiceResponse::notFound(
                message: 'No active discount found'
            );
        }

        return ServiceResponse::success(
            data: $activeDiscount
        );
    }

    public static function getAllDiscounts()
    {
        $discounts = Discount::getNonDeletedDiscounts();
        if ($discounts->isEmpty()) {
            return ServiceResponse::success(message: 'No discounts found');
        }

        return ServiceResponse::success(
            message: 'Discounts retrieved successfully',
            data: $discounts
        );
    }

    public static function addDiscount(array $data)
    {
        try {
            $discount = Discount::create($data);
        } catch (Exception $e) {
            Logger::error(
                message: 'Failed to create discount',
                exception: $e,
                extra: ['data' => $data]
            );
            throw new ServiceException(
                message: 'Failed to create discount',
                data: $data
            );
        }

        return ServiceResponse::created(
            data: $discount,
            message: 'Discount added successfully'
        );
    }

    public static function getDiscountPercentage()
    {
        $activeDiscount = Discount::where('is_active', true)->first();
        if (!$activeDiscount) {
            return ServiceResponse::success(data: ['percentage' => 0]);
        }

        return ServiceResponse::success(
            data: ['percentage' => $activeDiscount->percentage]
        );
    }

    public static function updateDiscount($discountId, array $data)
    {
        try {
            $discount = Discount::getDiscountById($discountId);

            if (!$discount) {
                return ServiceResponse::notFound(message: 'Discount not found');
            }

            $activeDiscount = Discount::getActiveDiscount();
            if (
                $activeDiscount
                && $activeDiscount->id != $discountId
                && isset($data['is_active'])
                && $data['is_active']
            ) {
                return ServiceResponse::badRequest(
                    message: 'Another discount is already active',
                    data: ['active_discount' => $activeDiscount]
                );
            }

            $discount->update($data);
        } catch (Exception $e) {
            Logger::error(
                message: 'Failed to update discount',
                exception: $e,
                extra: ['data' => $data]
            );
            throw new ServiceException(
                message: 'Failed to update discount',
            );
        }

        return ServiceResponse::success(
            message: 'Discount updated successfully',
            data: $discount
        );
    }

    public static function deleteDiscount($discountId)
    {
        $discount = Discount::getDiscountById($discountId);

        if (!$discount) {
            return ServiceResponse::notFound(
                message: 'Discount not found'
            );
        }

        $discount->is_deleted = true;
        $discount->save();

        return ServiceResponse::success(
            message: 'Discount deleted successfully'
        );
    }
}
