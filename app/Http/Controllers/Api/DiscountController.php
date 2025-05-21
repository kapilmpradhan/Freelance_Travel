<?php

namespace App\Http\Controllers\Api;

use App\Models\Discount;
use App\Services\DiscountService;
use App\Services\ServiceException;
use Illuminate\Support\Facades\Validator;

class DiscountController extends BaseController
{
    public function getDiscount()
    {
        $discountResponse = DiscountService::getActiveDiscountData();
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
}
