<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ItemType;
use App\Models\UserOrderCommission;
use Exception;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\DTOs\AddToQuote;
use App\Models\Product;
use App\Models\Quote;
use App\Services\BookingService;
use App\Services\CartItemService;
use App\Services\CartItemServiceV2;
use App\Services\DiscountService;
use App\Services\ProductCategoryService;
use App\Services\RedeemerService;
use App\Services\ServiceException;
use App\Services\ServiceResponse;
use App\Services\TdmsService;
use App\Services\UserOrderCommissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CartItemController extends BaseController
{
    public function addItemsToCartV2(Request $request): JsonResponse
    {
        $data = $request->all();

        $validator = Validator::make($data, CartItem::saveItemsV2Rule());
        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $agentBranchCode = $request->agentBranchCode;
        $isDryRun = $request->query('dry') == 1;
        $sessionId = $request->query('sessionId');

        if (!$request->user && !$sessionId) {
            return $this->sendError('Unauthorized user', [], 401);
        }

        if (!$request->user) {
            $itemType = ItemType::session($sessionId);
        } else {
            $itemType = $isDryRun ? ItemType::dry($data) : ItemType::cart();
        }
        $itemType->agentBranchCode = $agentBranchCode;

        try {
            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $request->user ? $request->user->uuid : null,
                tdmsProductId: $data['tdmsProductId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                productPricesDetails: $data['productPricesDetails'],
                addToQuote: null,
                itemType: $itemType,
                isDryRun: $isDryRun,
            );

            if ($saveItemsResponse->isSuccess()) {
                Logger::debug('Items added to cart', [
                    'log_file' => config('logging.log_files.cart'),
                    'user_id' => $request->user ? $request->user->uuid : null,
                    'tdms_product_id' => $data['tdmsProductId'],
                    'action' => 'cart_items_added',
                ]);
            }

            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to add items to cart';
            Logger::error($errorMessage, $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $request->user ? $request->user->uuid : null,
                'action' => 'cart_items_add_failed',
            ]);
            return $this->sendError($errorMessage);
        }
    }

    public function addItemsInNewQuoteV2(Request $request): JsonResponse
    {
        $data = $request->all();
        $validated = Validator::make($data, CartItem::saveItemsInNewQuoteV2());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        $validateQuoteTitle = Validator::make(
            ['quoteTitle' => $data['quoteTitle']],
            ['quoteTitle' => 'required|string|max:200']
        );

        $userAvailableQuotes = Quote::where('user_id', $request->user->uuid)
            ->where('user_order_id', null)
            ->where('title', $data['quoteTitle'])
            ->exists();

        if ($userAvailableQuotes) {
            return $this->sendError('Validation Error.', ['quoteTitle' => ['The quote title has already been taken.']]);
        }

        if ($validateQuoteTitle->fails()) {
            return $this->sendError('Validation Error.', $validateQuoteTitle->errors());
        }

        $addToQuote = AddToQuote::new(title: $data['quoteTitle']);
        try {
            $itemType = ItemType::quote($addToQuote->quoteId);
            $itemType->agentBranchCode = $request->agentBranchCode;

            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                addToQuote: $addToQuote,
                itemType: $itemType,
                productPricesDetails: $data['productPricesDetails']
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to create new quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function addItemsInExistingQuoteV2(Request $request, string $quoteId): JsonResponse
    {
        $data = $request->all();
        $validated = Validator::make($data, CartItem::saveItemsV2Rule());

        if ($validated->fails()) {
            return $this->sendError('Validation Error.', $validated->errors());
        }

        try {
            $itemType = ItemType::quote($quoteId);
            $itemType->agentBranchCode = $request->agentBranchCode;

            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $request->user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                productPricesDetails: $data['productPricesDetails'],
                addToQuote: AddToQuote::existing(quoteId: $quoteId),
                itemType: $itemType
            );
            return $this->sendResponseFromService($saveItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to add items to quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function addExistingCartItemsToQuote(Request $request, string|null $quoteId = null): JsonResponse
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

    public function setCustomers(Request $request): JsonResponse
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

    public function getCustomers(Request $request): JsonResponse
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

    public function getQuotes(Request $request): JsonResponse
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

    public function getAllQuotes(Request $request): JsonResponse
    {
        $user = $request->user;
        try {
            $getQuotesResponse = CartItemService::getAllQuotes(user: $user);
            return $this->sendResponseFromService($getQuotesResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get quotes';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getMyQuotes(Request $request): JsonResponse
    {
        $user = $request->user;
        try {
            $getQuotesResponse = CartItemService::getMyQuotes(user: $user);
            return $this->sendResponseFromService($getQuotesResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get quotes';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function shareQuote(Request $request, string $quoteId): JsonResponse
    {
        $user = $request->user;
        $agentType = app('agentType');
        if ($agentType->isDefaultAgent) {
            return $this->sendError('Default agent users cannot share quote');
        }

        $data = $request->all();
        $isRedeemer = $request->query->has('isRedeemer') && $request->query('isRedeemer');

        if ($isRedeemer) {
            $validator = Validator::make($data, [
                'redeemerId' => 'required|integer',
            ]);
        } else {
            $validator = Validator::make($data, [
                'shareToEmail' => 'required|email',
            ]);
        }

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        if ($isRedeemer) {
            $shareToEmail = CartCustomerDetail::where('id', $data['redeemerId'])
                ->where('user_id', $user->uuid)
                ->where('is_deleted', false)
                ->value('email');
            if (!$shareToEmail) {
                return $this->sendError('Redeemer not found');
            }
        } else {
            $shareToEmail = $data['shareToEmail'];
        }

        if ($shareToEmail == $user->email) {
            return $this->sendError('Cannot share quote to yourself');
        }

        try {
            $agentType = app('agentType');
            $shareQuoteResponse = CartItemServiceV2::shareQuote(
                sharedByUserId: $user->uuid,
                sharedToEmail: $shareToEmail,
                quoteId: $quoteId,
                agentType: $agentType
            );
            return $this->sendResponseFromService($shareQuoteResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to share quote';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getQuoteSharedUsers(Request $request, string $quoteId): JsonResponse
    {
        try {
            $getSharedUsersResponse = CartItemServiceV2::getQuoteSharedUsers(
                quoteId: $quoteId
            );
            return $this->sendResponseFromService($getSharedUsersResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get shared users';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getQuotesSharedToMe(Request $request): JsonResponse
    {
        $user = $request->user;
        try {
            $getSharedQuotesResponse = CartItemServiceV2::getQuotesSharedToMe(user: $user);
            return $this->sendResponseFromService($getSharedQuotesResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get shared quotes';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function acceptQuoteInvite(Request $request, $quoteShareId): JsonResponse
    {
        $user = $request->user;
        try {
            $acceptInviteResponse = CartItemServiceV2::acceptQuoteInvite(user: $user, quoteShareId: $quoteShareId);
            return $this->sendResponseFromService($acceptInviteResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to accept invite';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function rejectQuoteInvite(Request $request, $quoteShareId): JsonResponse
    {
        $user = $request->user;
        try {
            $rejectInviteResponse = CartItemServiceV2::rejectQuoteInvite(user: $user, quoteShareId: $quoteShareId);
            return $this->sendResponseFromService($rejectInviteResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to reject invite';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function setQuoteCustomers(Request $request, string $quoteId): JsonResponse
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

    public function getQuoteCustomers(Request $request, string $quoteId): JsonResponse
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

    public function getItemsInCart(Request $request): JsonResponse
    {
        $userId = $request->user ? $request->user->uuid : null;
        $sessionId = $request->query('sessionId');

        if (!$userId && !$sessionId) {
            return $this->sendError('Unauthorized user', [], 401);
        }

        $itemType = $sessionId ? ItemType::session($sessionId) : ItemType::cart();
        try {
            $getCartItemsResponse = CartItemService::getItemsInCartOrQuote(userId: $userId, itemType: $itemType);
            return $this->sendResponseFromService($getCartItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to get items in cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getItemsInQuote(Request $request, string $quoteId): JsonResponse
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

    public function getQuoteDetails(Request $request, string $quoteId): JsonResponse
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

    public function setBookingData(Request $request): JsonResponse
    {
        $user = $request->user;
        $quoteId = $request->query('quoteId');
        $sessionId = $request->query('sessionId');

        if (!$user && !$sessionId) {
            return $this->sendError('Unauthenticated user', [], 401);
        }

        if ($quoteId) {
            $itemType = ItemType::quote($quoteId);
            $cartItems = CartItem::userQuoteItems($user->uuid, $itemType);
        } else {
            $itemType = $sessionId ? ItemType::session($sessionId) : ItemType::cart();
            $cartItems = CartItem::userItems($user ? $user->uuid : null, $itemType);
        }

        $data = json_decode($request->getContent(), true);
        $validator = Validator::make($data, CartItem::updateItemBookingDataRule());

        $validator->after(function ($validator) use ($data, $user, $itemType, $cartItems) {
            $cartItemIds = $cartItems->pluck('id')->toArray();
            if (empty($cartItemIds)) {
                $validator->errors()->add('cartItems', 'No cart items found');
                return;
            }
            $productIds = $cartItems->unique()->pluck('tdms_product_id')->toArray();
            $products = Product::whereIn('tdms_product_id', $productIds);

            $userRedeemersResponse = RedeemerService::listActiveRedeemers($user ? $user->uuid : null, $itemType);
            $userRedeemerIds = $userRedeemersResponse->data->pluck('id')->toArray();

            foreach ($data as $item) {
                $cartItem = $cartItems->where('id', $item['cartItemId'])->first();
                // Check if cartItemId provided exists in user cart
                if (!$cartItem) {
                    $validator->errors()->add(
                        $item['cartItemId'],
                        'Cart item not found'
                    );
                    continue;
                };

                $product = (clone $products)->where('tdms_product_id', $cartItem->tdms_product_id)->first();
                $farePrices = $product->json['faresprices'];

                foreach ($farePrices as $fare) {
                    if ((string) $fare["productPricesDetailsId"] === (string) $cartItem->product_price_details_id) {
                        break;
                    }
                }

                if (
                    isset($fare['fareQtyRestrictions'])
                    && (int) $item['quantity'] % (int) $fare['fareQtyRestrictions'] != 0
                ) {
                    $validator->errors()->add(
                        $item['cartItemId'],
                        'Quantity must be multiple of ' . $fare['fareQtyRestrictions']
                    );
                }

                // Check if number of bookingData provided is same as quantity
                if (
                    (int) $fare['numPax'] == 1
                    && isset($item['quantity']) && isset($item['bookingData'])
                    && $item['quantity'] != count($item['bookingData'])
                ) {
                    $validator->errors()->add(
                        $item['cartItemId'] . '.bookingData',
                        'bookingData items count should be same as quantity'
                    );
                } elseif (
                    (int) $fare['numPax'] > 1
                    && isset($item['quantity']) && isset($item['bookingData'])
                    && count($item['bookingData']) != $item['quantity'] / (int) $fare['numPax']
                ) {
                    $validator->errors()->add(
                        $item['cartItemId'] . '.bookingData',
                        'Invalid number of bookingData items provided'
                    );
                }

                // Check if redeemers with provided id are available
                foreach ($item['bookingData'] as $bookingData) {
                    $notAvailableRedeemers = array_diff($bookingData['redeemers'] ?? [], $userRedeemerIds);
                    if (!empty($notAvailableRedeemers)) {
                        $bookingDataIndex = array_search($bookingData, $item['bookingData']);
                        $validator->errors()->add(
                            $item['cartItemId'] . '.bookingData.redeemers.' . $bookingDataIndex,
                            "redeemer id" . json_encode($notAvailableRedeemers) . " not available"
                        );
                    }
                }
            }
        });

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        try {
            $updateItemBookingDataResponse = CartItemServiceV2::updateItemBookingData(
                data: $validator->validated()
            );
            $isQuantityChanged = $updateItemBookingDataResponse->data['isQuantityChanged'];
            if ($isQuantityChanged && !$itemType->isSession) {
                $orderCommissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
                    $user->uuid,
                    $itemType
                );
                if ($orderCommissionResponse->isSuccess()) {
                    $orderCommission = $orderCommissionResponse->data; /** @var UserOrderCommission $orderCommission */
                    $orderCommission->percentage = null;
                    $orderCommission->points_available = null;
                    $orderCommission->save();
                }
            }

            return $this->sendResponseFromService($updateItemBookingDataResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to set booking data';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function removeItemFromCart(Request $request, int $cartItemId): JsonResponse
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

    public function removeItemsFromCart(Request $request): JsonResponse
    {
        $userId = $request->user ? $request->user->uuid : null;
        $isCart = $request->query('isCart') == 1;
        $cartItemId = $request->query('cartItemId');
        $groupId = $request->query('groupId');
        $productId = $request->query('productId');
        $sessionId = $request->query('sessionId');

        if (!$userId && !$sessionId) {
            return $this->sendError('Unauthorized user', [], 401);
        }

        if ($isCart) {
            $type = ItemType::cart();
        } elseif ($groupId) {
            $type = ItemType::group($groupId);
        } elseif ($cartItemId) {
            $type = ItemType::cartItem($cartItemId);
        } elseif ($productId) {
            $type = ItemType::product($productId);
        } elseif ($sessionId) {
            $type = ItemType::session($sessionId);
        } else {
            return $this->sendError('Invalid request');
        }

        try {
            $removeResponse = CartItemService::removeItemsFromCart(
                userId: $userId,
                type: $type,
            );
            return $this->sendResponseFromService($removeResponse);
        } catch (Exception $e) {
            $errorMessage = "Failed to remove item from " . ($request->is('quotes/*') ? "quote" : "cart");
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function removeQuote(Request $request, int $quoteId): JsonResponse
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

    public function removeItemsFromQuote(Request $request): JsonResponse
    {
        $userId = $request->user->uuid;
        $quoteId = $request->query('quoteId');
        $quoteItemId = $request->query('quoteItemId');
        $groupId = $request->query('groupId');
        $productId = $request->query('productId');

        if (!$quoteId) {
            return $this->sendError('Quote id is required');
        } elseif ($quoteId) {
            $type = ItemType::quote($quoteId);
        }

        if ($quoteItemId) {
            $type = ItemType::quoteItem($quoteId, $quoteItemId);
        } elseif ($groupId) {
            $type = ItemType::group($quoteId, $groupId);
        } elseif ($quoteItemId) {
            $type = ItemType::quoteItem($quoteId, $quoteItemId);
        } elseif ($productId) {
            $type = ItemType::product($quoteId, $productId);
        }

        try {
            $removeResponse = CartItemService::removeItemsFromQuote(
                userId: $userId,
                type: $type,
            );
            return $this->sendResponseFromService($removeResponse);
        } catch (Exception $e) {
            $errorMessage = "Failed to remove item from " . ($request->is('quotes/*') ? "quote" : "cart");
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getCartItems(Request $request): JsonResponse
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

    public function removeCartItem(Request $request, $cartItemId): JsonResponse
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

    public function submitOrder(Request $request): JsonResponse
    {
        $data = $request->all();
        $sessionId = $request->get('sessionId');
        $validate = Validator::make($data, [
            "paymentType" => "required|in:email-quote,pay-now",
            "pointsApplied" => "nullable|numeric",
            "commissionApplied" => "nullable|numeric"
        ]);

        if ($validate->fails()) {
            return $this->sendError("Place order failed", $validate->errors());
        }
        $itemType = !is_null($sessionId) ? ItemType::session($sessionId) : ItemType::cart();
        $itemType->agentBranchCode = $request->agentBranchCode;

        try {
            $postOrderResponse = BookingService::postOrder(
                userId: $request->user->uuid,
                intent: $data['paymentType'],
                pointsApplied: $data['pointsApplied'] ?? null,
                commissionApplied: $data['commissionApplied'] ?? null,
                processAsQuote: true,
                itemType: $itemType,
                agentType: app('agentType')
            );

            if ($postOrderResponse->isSuccess()) {
                Logger::debug('Order submitted successfully', [
                    'log_file' => config('logging.log_files.order'),
                    'user_id' => $request->user->uuid,
                    'payment_type' => $data['paymentType'],
                    'action' => 'order_submitted',
                ]);
            }

            return $this->sendResponseFromService($postOrderResponse);
        } catch (ServiceException $e) {
            $errorMessage = "Failed to submit order";
            Logger::error($errorMessage, $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $request->user->uuid,
                'action' => 'order_submit_failed',
            ]);
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function submitQuoteOrder(Request $request, string $quoteId): JsonResponse
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            "pointsApplied" => "nullable|numeric",
            "commissionApplied" => "nullable|numeric"
        ]);

        if ($validate->fails()) {
            return $this->sendError("Place order failed", $validate->errors());
        }

        try {
            $itemType = ItemType::quote($quoteId);
            $itemType->agentBranchCode = $request->agentBranchCode;
            $postOrderResponse = BookingService::postOrder(
                userId: $request->user->uuid,
                intent: 'pay-now',
                pointsApplied: $data['pointsApplied'] ?? null,
                commissionApplied: $data['commissionApplied'] ?? null,
                processAsQuote: true,
                itemType: $itemType,
                agentType: app('agentType')
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

    public function directPurchaseV2(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->all();
        $validate = Validator::make($data, CartItem::directPurchaseRuleV2());

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
            $itemType = ItemType::direct();
            $itemType->agentBranchCode = $request->agentBranchCode;

            $saveItemsResponse = CartItemServiceV2::saveItems(
                userId: $user->uuid,
                tdmsProductId: $data['tdmsProductId'],
                startDate: $data['startDate'],
                days: $data['days'],
                selectedAvailableIndices: $data['selectedAvailableIndices'],
                productPricesDetails: $data['productPricesDetails'],
                addToQuote: null,
                itemType: $itemType
            );

            if (!$saveItemsResponse->isSuccess()) {
                DB::rollBack();
                return $this->sendResponseFromService($saveItemsResponse);
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
            $itemType = ItemType::direct();
            $itemType->agentBranchCode = $request->agentBranchCode;

            $postOrderResponse = BookingService::postOrder(
                userId: $user->uuid,
                intent: 'pay-now',
                pointsApplied: $data['pointsApplied'] ?? null,
                commissionApplied: $data['commissionApplied'] ?? null,
                processAsQuote: true,
                itemType: $itemType,
                agentType: app('agentType')
            );

            if (!$postOrderResponse->isSuccess()) {
                return $this->sendResponseFromService($postOrderResponse);
            }
            CartCustomerDetail::where('user_id', $user->uuid)
                            ->where('is_primary', false)
                            ->where('is_direct_purchase', true)
                            ->where('is_deleted', false)
                            ->whereNull('user_order_id')
                            ->update(['is_deleted' => true]);
        } catch (Exception $e) {
            $errorMessage = "Failed to direct purchase";
            Logger::error($errorMessage, $e);
            return $this->sendError($e->getMessage());
        }
        return $this->sendResponseFromService($postOrderResponse);
    }

    public function completeOrder(Request $request): JsonResponse
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

    public function getBookings(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $bookings = TdmsService::getCustomerBookings($user->email);
            if (!$bookings) {
                return $this->sendError('Could not fetch customer bookings');
            }

            $orders = $bookings->data['orders'];
            foreach ($orders as $orderKey => &$order) {
                foreach ($order['products'] as $productKey => &$product) {
                    foreach ($product['redeemers'] as $redeemerKey => &$redeemer) {
                        foreach ($redeemer['bookingDetails'] as $detailKey => $bookingDetail) {
                            if ($bookingDetail['travelDate'] == null) {
                                unset($redeemer['bookingDetails'][$detailKey]);
                            }
                        }
                        if (empty($redeemer['bookingDetails'])) {
                            unset($product['redeemers'][$redeemerKey]);
                        }
                    }
                    if (empty($product['redeemers'])) {
                        unset($order['products'][$productKey]);
                    }
                }
                if (empty($order['products'])) {
                    unset($orders[$orderKey]);
                }
            }

            $orders = array_values($orders);
            return $this->sendResponse(
                title: 'User bookings',
                data: ['has_more' => false, 'orders' => $orders]
            );
        } catch (Exception $e) {
            $errorMessage = 'Failed to get customer bookings';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function getAllUserOrders(Request $request)
    {
        $agentType = app('agentType');
        if ($agentType->isPointsAgent || $agentType->isCommissionAgent) {
            try {
                $userOrdersResponse = TdmsService::getUserOrders($agentType->agent->access_token);
            } catch (ServiceException $e) {
                return $this->sendResponseFromService($e->toServiceResponse());
            }
            return $this->sendResponseFromService($userOrdersResponse);
        }

        return ServiceResponse::badRequest('Need to upgrade to points agent.');
    }

    public function getCartDetailsByBookingReference(Request $request, $bookingReference): JsonResponse
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

    public function getCustomerOrderDetail(Request $request, $bookingReference): JsonResponse
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

    public function categories(Request $request): JsonResponse
    {
        $getProductCategories = ProductCategoryService::getCategories();

        return $this->sendResponseFromService($getProductCategories);
    }

    public function getDiscountPercentage(Request $request): JsonResponse
    {
        $userId = $request->user->uuid;
        $isCart = $request->query('isCart') == 1;
        $quoteId = $request->query('quoteId');
        $isDirect = $request->query('isDirect') == 1;
        $pointsApplied = $request->query('pointsApplied');


        if ($isCart) {
            $itemType = ItemType::cart();
        } elseif ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } elseif ($isDirect) {
            $itemType = ItemType::direct();
        } else {
            return $this->sendError('Invalid request. Please provide either isCart, quoteId or isDirect parameter.');
        }

        $orderDiscountResponse = DiscountService::getItemsDiscount(
            itemType: $itemType,
            userId: $userId,
            agentType: app('agentType'),
            pointsApplied: $pointsApplied
        );

        return $this->sendResponseFromService($orderDiscountResponse);
    }

    public function getDiscountPercentageV2(Request $request): JsonResponse
    {
        $datas = json_decode($request->getContent(), true) ?? [];

        $itemType = ItemType::discount();
        if ($request->query('isCart') ?? null) {
            $itemType->isCart = true;
        } elseif ($request->query('quoteId') ?? null) {
            $itemType->isQuote = true;
            $itemType->typeId = $request->query('quoteId');
        } elseif ($request->query('dry') ?? null) {
            $itemType->isDry = true;
        } elseif ($request->query('sessionId') ?? null) {
            $itemType->isSession = true;
            $itemType->typeId = $request->get('sessionId');
        } else {
            return $this->sendError('isCart, quoteId or dry parameter is required');
        }

        if ($itemType->isCart || $itemType->isQuote) {
            $commissionResponse = UserOrderCommissionService::getOrSetCommissionOfUserCartOrQuote(
                userId: $request->user->uuid,
                itemType: $itemType,
                agentType: app('agentType')
            );
        } elseif ($itemType->isDry || $itemType->isSession) {
            $validator = Validator::make($datas, CartItem::calculateCommissionRule());
            if ($validator->fails() || empty($datas)) {
                return $this->sendError("Get discount percentage failed", $validator->errors());
            }

            $itemType->data = $datas;
            $commissionResponse = UserOrderCommissionService::getCommissionForDry(
                userId: $request->user ? $request->user->uuid : null,
                itemType: $itemType,
                agentType: app('agentType')
            );
        }

        return $this->sendResponse(
            title: 'Applicable discount',
            data: [
                'applicableDiscount' => round(DiscountService::calcuateOverallDiscount(
                    commissionPercentage: $commissionResponse->data['commission'],
                    platform: app('agentType')->platform,
                    user: $request->user
                ), 2),
                'pointsAvailable' => (float) $commissionResponse->data['pointsAvailable'] ?? 0,
            ]
        );
    }

    public function updateWithLatestDetails(Request $request): JsonResponse
    {
        $isCart = $request->query('isCart') == 1;
        $quoteId = $request->query('quoteId');
        if ($isCart) {
            $itemType = ItemType::cart();
        } elseif ($quoteId) {
            $itemType = ItemType::quote($quoteId);
        } else {
            return $this->sendError('Invalid request. Please provide either isCart or quoteId parameter.');
        }
        try {
            $updateItemsResponse = CartItemService::updateWithLatestDetails(user: $request->user, itemType: $itemType);
            return $this->sendResponseFromService($updateItemsResponse);
        } catch (Exception $e) {
            $errorMessage = 'Failed to update items in cart';
            Logger::error($errorMessage, $e);
            return $this->sendError($errorMessage);
        }
    }

    public function convertSessionItemsToCartItems(Request $request, $sessionId)
    {
        $userId = $request->user->uuid;
        $sessionItems = CartItem::userItems(null, ItemType::session($sessionId));

        DB::beginTransaction();
        CartCustomerDetail::where('session_id', $sessionId)
            ->update([
                'user_id' => $userId
            ]);

        foreach ($sessionItems as $item) {
            $item->user_id = $userId;
            $item->save();
        }

        DB::commit();

        return $this->sendResponse('Operation successful');
    }
}
