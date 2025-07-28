<?php

namespace App\Http\Controllers\Api;

use App\Models\Discount;
use Illuminate\Http\Request;
use App\Services\DiscountService;
use App\Services\ServiceException;
use Illuminate\Support\Facades\Validator;

class DiscountController extends BaseController
{
    public function getDiscount(Request $request)
    {
        $user = $request->user;
        $discountResponse = DiscountService::getActiveDiscountData($user);
        return $this->sendResponseFromService($discountResponse);
    }

    public function getAllDiscounts()
    {
        $discountResponse = DiscountService::getAllDiscounts();
        return $this->sendResponseFromService($discountResponse);
    }

    public function addNewDiscount()
    {
        $data = request()->all();
        $validator = Validator::make($data, Discount::addDiscountRule());
        if ($validator->fails()) {
            return $this->sendError(
                title: 'Validation Error',
                data: $validator->errors(),
                code: 400
            );
        }

        try {
            $addDiscountResponse = DiscountService::addDiscount($data);
            return $this->sendResponseFromService(
                $addDiscountResponse
            );
        } catch (ServiceException $e) {
            return $this->sendResponseFromService(
                $e->toServiceResponse()
            );
        }
    }

    public function updateDiscount($discountId)
    {
        $data = request()->all();
        $validator = Validator::make($data, Discount::updateDiscountRule());
        if ($validator->fails()) {
            return $this->sendError(
                title: 'Validation Error',
                data: $validator->errors(),
                code: 400
            );
        }

        try {
            $updateDiscountResponse = DiscountService::updateDiscount($discountId, $data);
            return $this->sendResponseFromService(
                $updateDiscountResponse
            );
        } catch (ServiceException $e) {
            return $this->sendResponseFromService(
                $e->toServiceResponse()
            );
        }
    }

    public function deleteDiscount($discountId)
    {
        $deleteDiscountResponse = DiscountService::deleteDiscount($discountId);
        return $this->sendResponseFromService(
            $deleteDiscountResponse
        );
    }
}
