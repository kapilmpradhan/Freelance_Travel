<?php

namespace App\Http\Controllers\Api;

use App\Logging\Logger;
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

        Logger::debug('Get discount request', [
            'log_file' => config('logging.log_files.discount'),
            'user_id' => $user?->uuid,
            'action' => 'get_discount_request',
        ]);

        $discountResponse = DiscountService::getActiveDiscountData($user);
        return $this->sendResponseFromService($discountResponse);
    }

    public function getAllDiscounts()
    {
        Logger::debug('Get all discounts request', [
            'log_file' => config('logging.log_files.discount'),
            'action' => 'get_all_discounts_request',
        ]);

        $discountResponse = DiscountService::getAllDiscounts();
        return $this->sendResponseFromService($discountResponse);
    }

    public function addNewDiscount()
    {
        $data = request()->all();

        Logger::debug('Add new discount request', [
            'log_file' => config('logging.log_files.discount'),
            'title' => $data['title'] ?? null,
            'action' => 'add_discount_request',
        ]);

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

        Logger::debug('Update discount request', [
            'log_file' => config('logging.log_files.discount'),
            'discount_id' => $discountId,
            'action' => 'update_discount_request',
        ]);

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
        Logger::debug('Delete discount request', [
            'log_file' => config('logging.log_files.discount'),
            'discount_id' => $discountId,
            'action' => 'delete_discount_request',
        ]);

        $deleteDiscountResponse = DiscountService::deleteDiscount($discountId);
        return $this->sendResponseFromService(
            $deleteDiscountResponse
        );
    }
}
