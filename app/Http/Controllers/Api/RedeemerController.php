<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use App\Http\Controllers\Api\BaseController;
use App\Logging\Logger;
use App\Models\CartCustomerDetail;
use App\Services\RedeemerService;
use App\Services\ServiceException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Exception;

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
            if ($sessionId) {
                $itemType->isSession = true;
                $itemType->typeId = $sessionId;
            }
        } else {
            $itemType = $userId ? ItemType::cart() : ItemType::session($sessionId);
        }

        try {
            $addRedeemerResponse = RedeemerService::addNewRedeemer(
                userId: $userId,
                redeemerData: $data,
                itemType: $itemType,
            );

            return $this->sendResponseFromService($addRedeemerResponse);
        } catch (ServiceException $e) {
            Logger::error('Failed to add redeemer', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'action' => 'add_redeemer_failed',
            ]);
            return $this->sendResponseFromService($e->toServiceResponse());
        } catch (Exception $e) {
            $errorMessage = 'Failed to add redeemer';
            Logger::error($errorMessage, $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'action' => 'add_redeemer_failed',
            ]);
            return $this->sendError($errorMessage);
        }
    }

    public function listRedeemers(Request $request)
    {
        $user = $request->user;
        $sessionId = $request->get('sessionId');

        Logger::debug('List redeemers request', [
            'log_file' => config('logging.log_files.user_profile'),
            'user_id' => $user ? $user->uuid : null,
            'action' => 'list_redeemers_request',
        ]);

        if (!$user && !$sessionId) {
            return $this->sendError('Unauthenticated user', [], 401);
        }

        $quoteId = $request->get('quoteId');
        $isDirectPurchase = $request->get('isDirectPurchase', false);

        if ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } elseif ((int) $isDirectPurchase == 1) {
            $itemType = ItemType::direct();
            if ($sessionId) {
                $itemType->isSession = true;
                $itemType->typeId = $sessionId;
            }
        } else {
            $itemType = $user ? ItemType::cart() : ItemType::session($sessionId);
        }

        $userId = $user ? $user->uuid : null;

        try {
            $listRedeemersResponse = RedeemerService::listActiveRedeemers($userId, $itemType);

            return $this->sendResponseFromService($listRedeemersResponse);
        } catch (ServiceException $e) {
            Logger::error('Failed to list redeemers', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'action' => 'list_redeemers_failed',
            ]);
            return $this->sendResponseFromService($e->toServiceResponse());
        } catch (Exception $e) {
            $errorMessage = 'Failed to list redeemers';
            Logger::error($errorMessage, $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'action' => 'list_redeemers_failed',
            ]);
            return $this->sendError($errorMessage);
        }
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

        try {
            $removeRedeemerResponse = RedeemerService::removeRedeemer(
                userId: $userId,
                sessionId: $sessionId,
                redeemerId: $redeemerId
            );

            return $this->sendResponseFromService($removeRedeemerResponse);
        } catch (ServiceException $e) {
            Logger::error('Failed to remove redeemer', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'redeemer_id' => $redeemerId,
                'action' => 'remove_redeemer_failed',
            ]);
            return $this->sendResponseFromService($e->toServiceResponse());
        } catch (Exception $e) {
            $errorMessage = 'Failed to remove redeemer';
            Logger::error($errorMessage, $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $userId,
                'redeemer_id' => $redeemerId,
                'action' => 'remove_redeemer_failed',
            ]);
            return $this->sendError($errorMessage);
        }
    }
}
