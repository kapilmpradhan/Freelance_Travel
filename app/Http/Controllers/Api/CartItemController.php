<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Logging\Logger;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\CacheProductJob;
use App\Jobs\UpdateUserCartItemProductsJob;
use App\Services\CartItemService;

class CartItemController extends BaseController
{
    public function addItemToCart(Request $request, CartItem $cartItem)
    {
        $data = $request->all();
        $data['user_id'] = $request->user->uuid;

        $validate = Validator::make($data, $cartItem->addProductsToCartRule());

        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors());
        }

        try {
            $cartItem = $cartItem->storeCartItem($request->user, $data);
            CacheProductJob::dispatch($request->user, $cartItem);
            return $this->sendResponse('Items added to cart', $cartItem->toArray());
        } catch (Exception $e) {
            Logger::error('Failed to add item to cart', $e);
            return $this->sendError($e->getMessage());
        }
    }

    public function addItemsToCart(Request $request)
    {
        $data = $request-> all();
        $validated = Validator::make($data, CartItem::saveItemsRule());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        try {
            $saveItemsResponse = CartItemService::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetailsId: $data['productPricesDetailsId'],
                timeId: $data['timeId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to add items to cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getItemsInCart(Request $request)
    {
        $userId = $request->user->uuid;
        try {
            $getCartItemsResponse = CartItemService::getItemsInCart(userId: $userId);
            return $this->sendResponseFromService($getCartItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get items in cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getCartItems(Request $request)
    {
        $userId = $request->user->uuid;

        try {
            $cartItems = CartItemService::getUserCartItemsWithProductDetails($userId);
            return $this->sendResponse('Cart items', $cartItems);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function removeCartItem(Request $request, $cartItemId)
    {
        try {
            $cartItem = CartItem::where('user_id', $request->user->uuid)
                            ->where('id', $cartItemId)->first();

            if (!$cartItem) {
                return $this->sendError('Cart item not found');
            }

            $cartItem->delete();
            return $this->sendResponse('Cart item deleted', [
                'id' => $cartItemId
            ]);
        } catch (Exception $e) {
            return $this->sendError('Error occured');
        }
    }
}
