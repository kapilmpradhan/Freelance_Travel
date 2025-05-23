<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendNotificationToTopic;
use App\Models\Discount;
use App\Services\DiscountService;
use App\Services\ServiceException;
use Illuminate\Support\Facades\Validator;

class AdminController extends BaseController
{
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

    public function sendNotificationToTopic()
    {
        $data = request()->all();
        $validator = Validator::make($data, [
            'title' => 'required|string',
            'description' => 'required|string',
            'topic' => 'required|string',
            'data' => 'array'
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                title: 'Validation Error',
                data: $validator->errors(),
                code: 400
            );
        }

        SendNotificationToTopic::dispatch(
            title: $data['title'],
            description: $data['description'],
            topic: $data['topic'],
            data: $data['data'] ?? []
        );

        return $this->sendResponse('Notification sent successfully');
    }
}
