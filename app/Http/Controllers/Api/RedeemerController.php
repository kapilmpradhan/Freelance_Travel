<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\CartCustomerDetail;
use App\Services\RedeemerService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RedeemerController extends BaseController
{
    public function addRedeemer(Request $request)
    {
        $data = $request->all();
        $userId = $request->user->uuid;

        $validator = Validator::make($data, CartCustomerDetail::addNewRedeemerRule());

        if ($validator->fails()) {
            return $this->sendResponse('Redeemer validation error', $validator->errors());
        }

        $addRedeemerResponse = RedeemerService::addNewRedeemer(
            userId: $userId,
            redeemerData: $data
        );

        return $this->sendResponseFromService($addRedeemerResponse);
    }

    public function listRedeemers(Request $request)
    {
        $userId = $request->user->uuid;
        $listRedeemersResponse = RedeemerService::listActiveRedeemers($userId);

        return $this->sendResponseFromService($listRedeemersResponse);
    }

    public function removeRedeemer(Request $request, $redeemerId)
    {
        $userId = $request->user->uuid;

        $removeRedeemerResponse = RedeemerService::removeRedeemer(
            userId: $userId,
            redeemerId: $redeemerId
        );

        return $this->sendResponseFromService($removeRedeemerResponse);
    }
}
