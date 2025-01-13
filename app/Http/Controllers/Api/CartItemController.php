<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\CacheProductJob;
use App\Jobs\UpdateUserCartItemProductsJob;
use App\Services\BookingService;
use App\Services\CartItemService;
use App\Models\UserOrder;
use App\Services\TdmsService;

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

    public function setCustomers(Request $request)
    {
        $userId = $request->user->uuid;
        $data = $request->all();
        $validator = CartCustomerDetail::validator(data: $data);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $setCustomersResponse = CartItemService::setCustomers(
                userId: $userId,
                data: $validator->validated()['items'],
            );
            return $this->sendResponseFromService($setCustomersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to set customers of cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getCustomers(Request $request)
    {
        $userId = $request->user->uuid;
        try {
            $getCartCustomersResponse = CartItemService::getCustomers(userId: $userId);
            return $this->sendResponseFromService($getCartCustomersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get cart customers details';
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

    public function setBookingData(Request $request, int $cartItemId)
    {
        $userId = $request->user->uuid;
        $data = $request->all();
        $validator = Validator::make($data, CartItem::updateItemBookingDataRule());

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $updateItemBookingDataResponse = CartItemService::updateItemBookingData(
                userId: $userId,
                cartItemId: $cartItemId,
                quantity: $validator->validated()['quantity'],
                bookingData: $validator->validated()['bookingData'] ?? [],
            );
            return $this->sendResponseFromService($updateItemBookingDataResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to set booking data';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function removeItemFromCart(Request $request, int $cartItemId)
    {
        $userId = $request->user->uuid;

        try {
            $removeResponse = CartItemService::removeItemFromCart(
                userId: $userId,
                cartItemId: $cartItemId,
            );
            return $this->sendResponseFromService($removeResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to remove item from cart';
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

    public function submitOrder(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, ["paymentType" => "required|in:email-quote,pay-now"]);

        if ($validate->fails()) {
            return $this->sendError("Place order failed", $validate->errors());
        }

        try {
            $postOrderResponse = BookingService::postOrder(
                userId: $request->user->uuid,
                intent: $data['paymentType'],
                processAsQuote: true,
            );
            return $this->sendResponseFromService($postOrderResponse);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        }
    }

    public function completeOrder(Request $request)
    {
        $params = $request->query();

        $validate = Validator::make($params, [
            "bookingReference" => "required|string"
        ]);

        if ($validate->fails()) {
            return $this->sendError('Complete order error', $validate->errors());
        }
        $validated = $validate->validated();

        try {
            BookingService::completeOrder(
                bookingReference: $validated['bookingReference'],
            );
            return $this->sendResponse('Order complete');
        } catch (Exception $e) {
            Logger::error(message: 'Order complete fail', exception: $e);
            return $this->sendError('Order complete fail');
        }
    }

    public function getBookings(Request $request)
    {
        $user = $request->user();
        $page = $request->query('page');
        $afterDate = $request->query('afterDate');

        $bookings = TdmsService::getCustomerBookings($user->email, $page, $afterDate);
        if (!$bookings) {
            return $this->sendError('Could not fetch customer bookings');
        }

        return $this->sendResponse('Customer bookings', $bookings);
    }
}
