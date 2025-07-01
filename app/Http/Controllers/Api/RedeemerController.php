<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
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
        $quoteId = $request->get('quoteId');
        $isDirectPurchase = $request->get('isDirectPurchase', false);

        $validator = Validator::make($data, CartCustomerDetail::addNewRedeemerRule());

        if ($validator->fails()) {
            return $this->sendError('Redeemer validation error', $validator->errors());
        }

        if ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } elseif ($isDirectPurchase) {
            $itemType = ItemType::direct();
        } else {
            $itemType = ItemType::cart();
        }

        $addRedeemerResponse = RedeemerService::addNewRedeemer(
            userId: $userId,
            redeemerData: $data,
            itemType: $itemType,
        );

        return $this->sendResponseFromService($addRedeemerResponse);
    }

    public function listRedeemers(Request $request)
    {
        $quoteId = $request->get('quoteId');
        $isDirectPurchase = $request->get('isDirectPurchase', false);

        if ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } elseif ((int) $isDirectPurchase == 1) {
            $itemType = ItemType::direct();
        } else {
            $itemType = ItemType::cart();
        }

        $userId = $request->user->uuid;
        $listRedeemersResponse = RedeemerService::listActiveRedeemers($userId, $itemType);

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
