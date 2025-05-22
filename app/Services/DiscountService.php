<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\Discount;
use Exception;

class DiscountService
{
    public static function getActiveDiscountData()
    {
        $activeDiscount = Discount::where('is_active', true)->first();
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
