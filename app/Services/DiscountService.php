<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\Enums\AgentBranchCode;
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
        Logger::debug('Getting active discount data', [
            'log_file' => config('logging.log_files.discount'),
            'user_id' => $user?->uuid,
            'action' => 'get_active_discount_data_start',
        ]);

        if ($user && Feature::for($user)->active('tester')) {
            $activeDiscount = Discount::where('is_test', true)->first();
        } else {
            $activeDiscount = Discount::where('is_active', true)
                ->where('platform', app('platform'))
                ->first();
        }
        if (!$activeDiscount) {
            $data = [
                'title' => null,
                'description' => null,
                'discount' => 0
            ];
            Logger::debug('No active discount found', [
                'log_file' => config('logging.log_files.discount'),
                'action' => 'no_active_discount',
            ]);
        } else {
            $data = [
                'title' => $activeDiscount->title,
                'description' => $activeDiscount->description,
                'discount' => $activeDiscount->percentage
            ];
            Logger::debug('Active discount data retrieved', [
                'log_file' => config('logging.log_files.discount'),
                'discount_id' => $activeDiscount->id,
                'percentage' => $activeDiscount->percentage,
                'action' => 'active_discount_retrieved',
            ]);
        }

        return ServiceResponse::success(
            data: $data
        );
    }

    public static function getOrderCommission(
        ItemType $itemType,
        $userId,
        $agentType,
        ?int $pointsApplied = null
    ): ServiceResponse {
        Logger::debug('Getting order commission', [
            'log_file' => config('logging.log_files.discount'),
            'user_id' => $userId,
            'item_type' => $itemType->isCart ? 'cart' : 'quote',
            'action' => 'get_order_commission_start',
        ]);

        $items = CartItem::userItems($userId, $itemType);
        if (count($items) == 0) {
            Logger::debug('No items available for commission calculation', [
                'log_file' => config('logging.log_files.discount'),
                'user_id' => $userId,
                'action' => 'no_items_for_commission',
            ]);
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
            $onlinePaymentMethod = BookingService::getOnlinePaymentMethod($agent->access_token, $agentType->platform);
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
                itemType: $itemType
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
                (float) $commission,
                $agent->points_multiplier
            );
            $orderCommission->save();

            Logger::debug('Order commission calculated', [
                'log_file' => config('logging.log_files.discount'),
                'user_id' => $userId,
                'commission_percentage' => $commissionPercentage,
                'action' => 'order_commission_calculated',
            ]);
        }
        return ServiceResponse::success($orderCommission);
    }

    // Calculates the overall discount based on the active discount percentage and commission percentage
    // e.g. If active discount is 10% and commission is 15%, the overall discount will be 10%
    // e.g. If active discount is 10% and commission is 12%, the overall discount will be 7% (12% - 5% threshold)
    // Reference link: blob:https://websitetravel.atlassian.net/854bd471-24c8-48b2-a606-745b19d8fa2e#media-blob-url=true&id=c0b28879-aa4e-4615-8fc6-7ec45200a9dd&collection=&contextId=22708&width=855&height=335&alt=
    public static function calcuateOverallDiscount($commissionPercentage, $platform, $threshold = 5, $user = null)
    {
        if (Feature::for($user)->active('tester')) {
            $activeDiscount = Discount::where('is_test', true)->first();
        } else {
            $activeDiscount = Discount::where('is_active', true)
                ->where('platform', $platform)
                ->first();
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
        $agentType,
        ?int $pointsApplied = null
    ): ServiceResponse {
        $activeDiscount = Discount::where('is_active', true)->first();
        if (!$activeDiscount || $activeDiscount->percentage == 0) {
            return ServiceResponse::success(
                data: ['applicableDiscount' => 0]
            );
        }

        $orderCommissionResponse = self::getOrderCommission($itemType, $userId, $agentType, $pointsApplied);
        if ($orderCommissionResponse->isError()) {
            return $orderCommissionResponse;
        }

        $agentBranch = $orderCommissionResponse->data->agent_branch;

        // TODO: Hard-coded branch code to FTX for now. Will update later!!
        if ($agentBranch !== AgentBranchCode::DEFAULT) {
            return ServiceResponse::success(data: ['discount' => 0]);
        }

        $applicableDiscount = self::calcuateOverallDiscount(
            commissionPercentage: $orderCommissionResponse->data->percentage,
            platform: $agentType->platform
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
        Logger::debug('Adding discount', [
            'log_file' => config('logging.log_files.discount'),
            'title' => $data['title'] ?? null,
            'percentage' => $data['percentage'] ?? null,
            'action' => 'add_discount_start',
        ]);

        try {
            $data['platform'] = app('platform');
            $discount = Discount::create($data);

            Logger::debug('Discount added', [
                'log_file' => config('logging.log_files.discount'),
                'discount_id' => $discount->id,
                'action' => 'add_discount_success',
            ]);
        } catch (Exception $e) {
            Logger::error(
                message: 'Failed to create discount',
                exception: $e,
                extra: ['data' => $data],
                data: [
                    'log_file' => config('logging.log_files.discount'),
                    'action' => 'add_discount_exception',
                ]
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
        Logger::debug('Updating discount', [
            'log_file' => config('logging.log_files.discount'),
            'discount_id' => $discountId,
            'action' => 'update_discount_start',
        ]);

        try {
            $discount = Discount::getDiscountById($discountId);

            if (!$discount) {
                Logger::debug('Discount not found for update', [
                    'log_file' => config('logging.log_files.discount'),
                    'discount_id' => $discountId,
                    'action' => 'update_discount_not_found',
                ]);
                return ServiceResponse::notFound(message: 'Discount not found');
            }

            $activeDiscount = Discount::getActiveDiscount();
            if (
                $activeDiscount
                && $activeDiscount->id != $discountId
                && isset($data['is_active'])
                && $data['is_active']
            ) {
                Logger::debug('Another discount already active', [
                    'log_file' => config('logging.log_files.discount'),
                    'discount_id' => $discountId,
                    'active_discount_id' => $activeDiscount->id,
                    'action' => 'update_discount_conflict',
                ]);
                return ServiceResponse::badRequest(
                    message: 'Another discount is already active',
                    data: ['active_discount' => $activeDiscount]
                );
            }

            $discount->update($data);

            Logger::debug('Discount updated', [
                'log_file' => config('logging.log_files.discount'),
                'discount_id' => $discountId,
                'action' => 'update_discount_success',
            ]);
        } catch (Exception $e) {
            Logger::error(
                message: 'Failed to update discount',
                exception: $e,
                extra: ['data' => $data],
                data: [
                    'log_file' => config('logging.log_files.discount'),
                    'discount_id' => $discountId,
                    'action' => 'update_discount_exception',
                ]
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
        Logger::debug('Deleting discount', [
            'log_file' => config('logging.log_files.discount'),
            'discount_id' => $discountId,
            'action' => 'delete_discount_start',
        ]);

        $discount = Discount::getDiscountById($discountId);

        if (!$discount) {
            Logger::debug('Discount not found for deletion', [
                'log_file' => config('logging.log_files.discount'),
                'discount_id' => $discountId,
                'action' => 'delete_discount_not_found',
            ]);
            return ServiceResponse::notFound(
                message: 'Discount not found'
            );
        }

        $discount->is_deleted = true;
        $discount->save();

        Logger::debug('Discount deleted', [
            'log_file' => config('logging.log_files.discount'),
            'discount_id' => $discountId,
            'action' => 'delete_discount_success',
        ]);
        return ServiceResponse::success(
            message: 'Discount deleted successfully'
        );
    }
}
