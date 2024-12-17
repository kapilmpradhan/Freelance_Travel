<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\CartItem;
use App\Models\AgentToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

        $agentToken = AgentToken::where('user_id', $request->user->uuid)->get();
        if (!$agentToken) {
            return $this->sendError('Agent token info', [
                "errorCode" => "100003",
                "errorMessage" => "Agent token not integrated"
            ]);
        }

        try {
            $cartItem = $cartItem->storeCartItem($request->user, $data);
            return $this->sendResponse('Items added to cart', $cartItem->toArray());
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function getCartItems(Request $request)
    {
        $userId = $request->user->uuid;

        $agentToken = AgentToken::where('user_id', $request->user->uuid)->first();
        if (!$agentToken) {
            return $this->sendError('Agent token info', [
                "errorCode" => "100003",
                "errorMessage" => "Agent token not integrated"
            ]);
        }

        try {
            $cartItems = CartItemService::getUserCartItemsWithProductDetails($userId);
            if ($cartItems) {
                UpdateUserCartItemProductsJob::dispatch($agentToken->access_token, $userId);
            }
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
