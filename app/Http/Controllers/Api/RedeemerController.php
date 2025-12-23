<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use App\Http\Controllers\Api\BaseController;
use App\Logging\Logger;
use App\Models\CartCustomerDetail;
use App\Services\RedeemerService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RedeemerController extends BaseController
{
    public function addRedeemer(Request $request)
    {
        $data = $request->all();
        $userId = $request->user ? $request->user->uuid : null;
        $sessionId = $request->get('sessionId');

        Logger::debug('Add redeemer request', [
            'log_file' => config('logging.log_files.user_profile'),
            'user_id' => $userId,
            'action' => 'add_redeemer_request',
        ]);

        if (!$userId && !$sessionId) {
            return $this->sendError('Unauthenticated user', [], 401);
        }

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
            $itemType = $userId ? ItemType::cart() : ItemType::session($sessionId);
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
        $user = $request->user;
        $sessoinId = $request->get('sessionId');

        Logger::debug('List redeemers request', [
            'log_file' => config('logging.log_files.user_profile'),
            'user_id' => $user ? $user->uuid : null,
            'action' => 'list_redeemers_request',
        ]);

        if (!$user && !$sessoinId) {
            return $this->sendError('Unauthenticated user', [], 401);
        }

        $quoteId = $request->get('quoteId');
        $isDirectPurchase = $request->get('isDirectPurchase', false);

        if ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } elseif ((int) $isDirectPurchase == 1) {
            $itemType = ItemType::direct();
        } else {
            $itemType = $user ? ItemType::cart() : ItemType::session($sessoinId);
        }

        $userId = $user ? $user->uuid : null;
        $listRedeemersResponse = RedeemerService::listActiveRedeemers($userId, $itemType);

        return $this->sendResponseFromService($listRedeemersResponse);
    }

    public function removeRedeemer(Request $request, $redeemerId)
    {
        $userId = $request->user ? $request->user->uuid : null;
        $sessionId = $request->get('sessionId');

        Logger::debug('Remove redeemer request', [
            'log_file' => config('logging.log_files.user_profile'),
            'user_id' => $userId,
            'redeemer_id' => $redeemerId,
            'action' => 'remove_redeemer_request',
        ]);

        if (!$userId && !$sessionId) {
            return $this->sendError('Unauthenticated user', [], 401);
        }

        $removeRedeemerResponse = RedeemerService::removeRedeemer(
            userId: $userId,
            sessionId: $sessionId,
            redeemerId: $redeemerId
        );

        return $this->sendResponseFromService($removeRedeemerResponse);
    }
}
