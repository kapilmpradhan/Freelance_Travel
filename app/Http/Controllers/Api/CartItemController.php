<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use Exception;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\DTOs\AddToQuote;
use App\Jobs\CacheProductJob;
use App\Services\BookingService;
use App\Services\CartItemService;
use App\Services\ProductCategoryService;
use App\Services\ServiceException;
use App\Services\TdmsService;
use Illuminate\Support\Facades\DB;

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
        $data = $request->all();
        $validated = Validator::make($data, CartItem::saveItemsRule());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        try {
            $isDryRun = $request->query('dry') == 1;

            $saveItemsResponse = CartItemService::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetailsId: $data['productPricesDetailsId'],
                timeId: $data['timeId'] ?? null,
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                bookingData: $data['bookingData'] ?? [],
                addToQuote: null,
                itemType: ItemType::cart(),
                isDryRun: $isDryRun,
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to add items to cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function addItemsInNewQuote(Request $request)
    {
        $data = $request->all();
        $validated = Validator::make($data, CartItem::saveItemsInNewQuote());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        $validateQuoteTitle = Validator::make(
            ['quoteTitle' => $data['quoteTitle']],
            ['quoteTitle' => 'required|string|max:200']
        );

        if ($validateQuoteTitle->fails()) {
            return $this->sendError('Validation Error.', $validateQuoteTitle->errors());
        }

        $addToQuote = AddToQuote::new(title: $data['quoteTitle']);
        try {
            $saveItemsResponse = CartItemService::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetailsId: $data['productPricesDetailsId'],
                timeId: $data['timeId'] ?? null,
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                bookingData: $data['bookingData'] ?? [],
                addToQuote: $addToQuote,
                itemType: ItemType::quote($addToQuote->quoteId)
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to create new quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function addItemsInExistingQuote(Request $request, string $quoteId)
    {
        $data = $request->all();
        $validated = Validator::make($data, CartItem::saveItemsRule());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        try {
            $saveItemsResponse = CartItemService::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetailsId: $data['productPricesDetailsId'],
                timeId: $data['timeId'] ?? null,
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                bookingData: $data['bookingData'] ?? [],
                addToQuote: AddToQuote::existing(quoteId: $quoteId),
                itemType: ItemType::quote($quoteId)
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to add items to quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function addExistingCartItemsToQuote(Request $request, string $quoteId = null)
    {
        if ($quoteId === null) {
            $data = $request->all();
            $validated = Validator::make($data, ['quoteTitle' => 'required|string|max:200']);
            if ($validated->fails()) {
                return $this->sendError('Validation Error.', $validated->errors());
            }
            $addToQuote = AddToQuote::new(title: $data['quoteTitle']);
        } else {
            $addToQuote = AddToQuote::existing(quoteId: $quoteId);
        };

        try {
            $convertItemsResponse = CartItemService::convertExistingCartItemsToQuote(
                userId: $request->user->uuid,
                addToQuote: $addToQuote,
            );
            return $this->sendResponseFromService($convertItemsResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function setCustomers(Request $request)
    {
        $userId = $request->user->uuid;
        $data = json_decode($request->getContent(), associative: true);
        $validator = CartCustomerDetail::validator(data: $data);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $setCustomersResponse = CartItemService::setCustomers(
                userId: $userId,
                data: $validator->validated()['items'],
                itemType: ItemType::cart()
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
            $getCartCustomersResponse = CartItemService::getCustomers(userId: $userId, itemType: ItemType::cart());
            return $this->sendResponseFromService($getCartCustomersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get cart customers details';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getQuotes(Request $request)
    {
        $userId = $request->user->uuid;
        try {
            $getQuotesResponse = CartItemService::getQuotes(userId: $userId);
            return $this->sendResponseFromService($getQuotesResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get quotes';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function setQuoteCustomers(Request $request, string $quoteId)
    {
        $userId = $request->user->uuid;
        $data = json_decode($request->getContent(), associative: true);
        $validator = CartCustomerDetail::validator(data: $data);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $setCustomersResponse = CartItemService::setCustomers(
                userId: $userId,
                data: $validator->validated()['items'],
                itemType: ItemType::quote($quoteId),
            );
            return $this->sendResponseFromService($setCustomersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to set customers of quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getQuoteCustomers(Request $request, string $quoteId)
    {
        $userId = $request->user->uuid;
        try {
            $getCartCustomersResponse = CartItemService::getCustomers(
                userId: $userId,
                itemType: ItemType::quote($quoteId)
            );
            return $this->sendResponseFromService($getCartCustomersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get quote customers details';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getItemsInCart(Request $request)
    {
        $userId = $request->user->uuid;
        try {
            $getCartItemsResponse = CartItemService::getItemsInCartOrQuote(userId: $userId, itemType: ItemType::cart());
            return $this->sendResponseFromService($getCartItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get items in cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getItemsInQuote(Request $request, string $quoteId)
    {
        $userId = $request->user->uuid;
        try {
            $getQuoteItemsResponse = CartItemService::getItemsInCartOrQuote(
                userId: $userId,
                itemType: ItemType::quote($quoteId)
            );
            return $this->sendResponseFromService($getQuoteItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get items in quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getQuoteDetails(Request $request, string $quoteId)
    {
        $data = ['quoteId' => $quoteId];
        $validator = Validator::make($data, ['quoteId' => 'integer|required']);
        if ($validator->fails()) {
            return $this->sendError('Invalid quoteId');
        }
        try {
            $getQuotesResponse = CartItemService::getQuoteDetails(quoteId: $quoteId);
            return $this->sendResponseFromService($getQuotesResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get quotes';
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
            $errorMessage = "Failed to remove item from " . ($request->is('quotes/*') ? "quote" : "cart");
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function removeQuote(Request $request, int $quoteId)
    {
        $userId = $request->user->uuid;
        try {
            $removeResponse = CartItemService::removeQuote(
                quoteId: $quoteId,
                userId: $userId
            );
            return $this->sendResponseFromService($removeResponse);
        } catch (Exception $e) {
            $errorMessage = "Failed to remove quote";
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
            $errorMessage = "Failed to get cart items";
            Logger::error($errorMessage, $e);
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
            $errorMessage = "Failed to remove cart items";
            Logger::error($errorMessage, $e);
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
        } catch (ServiceException $e) {
            $errorMessage = "Failed to submit order";
            Logger::error($errorMessage, $e);
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function submitOrderV2(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, ["paymentType" => "required|in:email-quote,pay-now"]);

        if ($validate->fails()) {
            return $this->sendError("Place order failed", $validate->errors());
        }

        try {
            $postOrderResponse = BookingService::postOrderV2(
                userId: $request->user->uuid,
                intent: $data['paymentType'],
                processAsQuote: true,
            );
            return $this->sendResponseFromService($postOrderResponse);
        } catch (ServiceException $e) {
            $errorMessage = "Failed to submit order";
            Logger::error($errorMessage, $e);
            return $this->sendResponseFromService($e->toServiceResponse());
        } catch (Exception $e) {
            $errorMessage = "Failed to submit order";
            Logger::error($errorMessage, $e);
            return $this->sendError($e->getMessage());
        }
    }

    public function submitQuoteOrder(Request $request, string $quoteId)
    {
        try {
            $postOrderResponse = BookingService::postOrderV2(
                userId: $request->user->uuid,
                intent: 'pay-now',
                processAsQuote: true,
                quoteId: $quoteId,
            );
            return $this->sendResponseFromService($postOrderResponse);
        } catch (ServiceException $e) {
            $errorMessage = "Failed to submit quote";
            Logger::error($errorMessage, $e);
            return $this->sendResponseFromService($e->toServiceResponse());
        } catch (Exception $e) {
            $errorMessage = "Failed to submit quote order";
            Logger::error($errorMessage, $e);
            return $this->sendError($e->getMessage());
        }
    }

    public function directPurchase(Request $request)
    {
        $user = $request->user();
        $data = $request->all();
        $validate = Validator::make($data, CartItem::directPurchaseRule());

        if ($validate->fails()) {
            return $this->sendError("Place order failed", $validate->errors());
        }

        try {
            $cleanResponse = CartItemService::cleanDirectPurchase($user->uuid);
            if (!$cleanResponse->isSuccess()) {
                return $this->sendResponseFromService($cleanResponse);
            }
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }

        DB::beginTransaction();
        try {
            $saveItemsResponse = CartItemService::saveItems(
                userId: $user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                productPricesDetailsId: $data['productPricesDetailsId'],
                timeId: $data['timeId'] ?? null,
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                bookingData: $data['bookingData'] ?? [],
                addToQuote: null,
                itemType: ItemType::direct()
            );

            if (!$saveItemsResponse->isSuccess()) {
                DB::rollBack();
                return $this->sendResponseFromService($saveItemsResponse);
            }

            if (isset($data['redeemers']) && count($data['redeemers']) > 0) {
                $setCustomersResponse = CartItemService::setCustomers(
                    userId: $user->uuid,
                    data: $data['redeemers'],
                    itemType: ItemType::direct()
                );

                if (!$setCustomersResponse->isSuccess()) {
                    DB::rollBack();
                    return $this->sendResponseFromService($setCustomersResponse);
                }
            }
        } catch (Exception $e) {
            DB::rollBack();
            $errorMessage = "Failed to direct purchase";
            Logger::error($errorMessage, $e);
            return $this->sendError($e->getMessage());
        }

        // Add item to database as BookingService::postOrderV2 service goes thorugh DB to get the items.
        DB::commit();

        try {
            $postOrderResponse = BookingService::postOrderV2(
                userId: $user->uuid,
                intent: 'pay-now',
                processAsQuote: true,
                isDirectPurchase: true
            );

            if (!$postOrderResponse->isSuccess()) {
                DB::rollBack();
                return $this->sendResponseFromService($postOrderResponse);
            }
        } catch (Exception $e) {
            $errorMessage = "Failed to direct purchase";
            Logger::error($errorMessage, $e);
            return $this->sendError($e->getMessage());
        }
        return $this->sendResponseFromService($postOrderResponse);
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

        $completeBookingResponse = CartItemService::completeBooking($validated['bookingReference']);
        if ($completeBookingResponse->isError()) {
            return $this->sendResponseFromService($completeBookingResponse);
        }

        return $this->sendResponseFromService($completeBookingResponse);
    }

    public function getBookings(Request $request)
    {
        $user = $request->user();
        $page = $request->query('page');
        $afterDate = $request->query('afterDate');

        try {
            $bookings = TdmsService::getCustomerBookings($user->email, $page, $afterDate);
            if (!$bookings) {
                return $this->sendError('Could not fetch customer bookings');
            }

            return $this->sendResponse('Customer bookings', $bookings);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get customer bookings';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getCartDetailsByBookingReference(Request $request, $bookingReference)
    {
        $userId = $request->user->uuid;
        try {
            $getCartItemsResponse = CartItemService::getCartItemsByBookingReference(
                userId: $userId,
                bookingReference: $bookingReference
            );
            return $this->sendResponseFromService($getCartItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get detail';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getCustomerOrderDetail(Request $request, $bookingReference)
    {
        $userId = $request->user->uuid;
        try {
            $getCustomerOrderResponse = CartItemService::getCustomerOrder(
                userId: $userId,
                bookingReference: $bookingReference
            );

            return $this->sendResponseFromService($getCustomerOrderResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get customer order';
            Logger::error('Customer order exception', $e);
            return $this->sendError($errorMessage);
        }
    }

    public function categories(Request $request)
    {
        $getProductCategories = ProductCategoryService::getCategories();

        return $this->sendResponseFromService($getProductCategories);
    }
}
