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
            return ServiceResponse::notFound();
        }

        $data = [
            'title' => $activeDiscount->title,
            'description' => $activeDiscount->description,
            'percentage' => $activeDiscount->percentage
        ];

        return ServiceResponse::success(
            data: $data
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
                code: 500,
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
}
