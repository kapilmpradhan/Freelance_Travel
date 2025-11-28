<?php

namespace App\Services;

use App\Models\Fareprice;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\DTOs\ItemType;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use App\Models\FarepriceHistory;
use App\Models\Product;
use App\Models\ProductHistory;
use App\Models\ProductPriceAvailability;
use App\Models\Quote;
use App\Models\ShareQuote;
use App\Models\UserOrder;
use App\Models\UserOrderCommission;
use Exception;

class CartItemService
{
    public static function cacheProduct(
        Product|null $product,
        array $latestProduct
    ) {
        if ($product && empty($latestProduct)) {
            ProductHistory::create([
                'tdms_product_id' => $product->tdms_product_id,
                'version' => $product->version,
                'tdms_product_last_update_date' => Carbon::now(),
                'json' => $product->json,
            ]);

            $product->delete();
        } elseif ($product && !empty($latestProduct)) {
            ProductHistory::create([
                'tdms_product_id' => $latestProduct['productId'],
                'version' => $product->version,
                'tdms_product_last_update_date' => Carbon::now(),
                'json' => $product->json,
            ]);

            $product->update([
                'json' => $latestProduct,
                'tdms_product_last_update_date' => Carbon::now(),
                'version' => $product->version + 1,
            ]);
        } elseif (!$product && !empty($latestProduct)) {
            Product::create([
                'tdms_product_id' => $latestProduct['productId'],
                'json' => $latestProduct,
                'tdms_product_last_update_date' => Carbon::now(),
            ]);
        }
    }

    public static function cacheProductV2($tdmsProductId, $agent, $latestProductDetails = null)
    {
        try {
            $cachedProduct = Product::where('tdms_product_id', $tdmsProductId)->first();
            $cachedFareprice = Fareprice::where('tdms_product_id', $tdmsProductId)
                ->where('agent_branch', $agent->branch_code)
                ->where('product_version', $cachedProduct?->version)
                ->first();

            $productLastUpdateDateResponse = ProductService::getProductsLastUpdate(
                agentToken: $agent->access_token,
                productIds: [$tdmsProductId]
            );
            if ($productLastUpdateDateResponse->responseCode == 404) {
                if ($cachedProduct) {
                    ProductHistory::create([
                        'tdms_product_id' => $tdmsProductId,
                        'version' => $cachedProduct->version,
                        'tdms_product_last_update_date' => $cachedProduct->tdms_product_last_update_date,
                        'json' => $cachedProduct->json,
                    ]);

                    $cachedProduct->delete();
                }

                if ($cachedFareprice) {
                    FarepriceHistory::create([
                        'tdms_product_id' => $tdmsProductId,
                        'json' => $cachedFareprice->json,
                        'agent_branch' => $cachedFareprice->agent_branch,
                        'product_version' => $cachedFareprice->product_version,
                    ]);

                    $cachedFareprice->delete();
                }

                return ServiceResponse::notFound('Product does not exist in TDMS');
            } elseif ($productLastUpdateDateResponse->isError()) {
                return $productLastUpdateDateResponse;
            }
            $productLastUpdate = $productLastUpdateDateResponse->data[$tdmsProductId];

            if ($cachedProduct && $productLastUpdate == $cachedProduct->tdms_product_last_update_date) {
                if (!$cachedFareprice) {
                    Fareprice::create([
                        'tdms_product_id' => $tdmsProductId,
                        'json' => $cachedProduct->json['faresprices'],
                        'agent_branch' => $agent->branch_code,
                        'product_version' => $cachedProduct->version,
                    ]);
                }
            } else {
                if (is_null($latestProductDetails)) {
                    $latestProductDetailsResponse = ProductService::getProductDetailsV2(
                        agentToken: $agent->access_token,
                        productIds: $tdmsProductId
                    );
                    if ($latestProductDetailsResponse->isError()) {
                        return $latestProductDetailsResponse;
                    }
                    $latestProductDetails = $latestProductDetailsResponse->data;
                }
                if ($cachedProduct) {
                    ProductHistory::create([
                        'tdms_product_id' => $tdmsProductId,
                        'version' => $cachedProduct->version,
                        'tdms_product_last_update_date' => $cachedProduct->tdms_product_last_update_date,
                        'json' => $cachedProduct->json,
                    ]);

                    $cachedProduct->update([
                        'json' => $latestProductDetails,
                        'tdms_product_last_update_date' => $productLastUpdate,
                        'version' => $cachedProduct->version + 1,
                    ]);
                    $latestCachedProduct = $cachedProduct;

                    if ($cachedFareprice) {
                        FarepriceHistory::create([
                            'tdms_product_id' => $tdmsProductId,
                            'json' => $cachedFareprice->json,
                            'agent_branch' => $cachedFareprice->agent_branch,
                            'product_version' => $cachedFareprice->product_version,
                        ]);

                        $cachedFareprice->update([
                            'json' => $latestProductDetails['faresprices'],
                            'product_version' => $latestCachedProduct->version,
                        ]);
                    } else {
                        Fareprice::create([
                            'tdms_product_id' => $tdmsProductId,
                            'json' => $latestProductDetails['faresprices'],
                            'agent_branch' => $agent->branch_code,
                            'product_version' => $latestCachedProduct->version,
                        ]);
                    }
                } else {
                    $latestCachedProduct = Product::create([
                        'tdms_product_id' => $tdmsProductId,
                        'json' => $latestProductDetails,
                        'tdms_product_last_update_date' => $productLastUpdate
                    ]);

                    Fareprice::create([
                        'tdms_product_id' => $tdmsProductId,
                        'json' => $latestProductDetails['faresprices'],
                        'agent_branch' => $agent->branch_code,
                        'product_version' => $latestCachedProduct->version,
                    ]);
                }
            }

            return ServiceResponse::success();
        } catch (Exception $e) {
            Logger::error('Unable to cache product', $e, data: ['tdmsProductId' => $tdmsProductId]);
            throw $e;
        }
    }

    public static function buildCartItemsData(
        string $userId,
        int $tdmsProductId,
        int|null $productVersion,
        int $productPricesDetailsId,
        array $bookingData,
        string $startDate,
        int $days,
        array $selectedAvailableIndices,
        array $productAvailabilities,
        Carbon $availabilityLastUpdatedAt,
        ItemType $itemType,
        array $productBookingDetails,
        ?Quote $quote,
    ): array {
        $cartItemsData = [];
        foreach ($selectedAvailableIndices as $selectedIndex) {
            $availability = $productAvailabilities[$selectedIndex];
            $newCartData = [
                'user_id' => $userId,
                'tdms_product_id' => $tdmsProductId,
                'product_version' => $productVersion,
                'product_price_details_id' => $productPricesDetailsId,
                'booking_date' => BaseService::stringToDate($availability['BookingDate']),
                'start_date' => BaseService::stringToDate($startDate),
                'days' => $days,
                'selected_index' => $selectedIndex,
                'availability' => $availability,
                'availability_last_updated_at' => $availabilityLastUpdatedAt,
                'booking_details' => $productBookingDetails,
                'booking_data' => $bookingData,
                'is_direct_purchase' => $itemType->isDirect,
            ];
            if (!is_null($quote)) {
                $newCartData['quote_id'] = $quote->id;
            }
            array_push($cartItemsData, $newCartData);
        }
        return $cartItemsData;
    }

    public static function getItemsInCartOrQuote($userId, ItemType $itemType)
    {
        $cartItems = CartItem::userItems($userId, $itemType);
        if ($cartItems->isEmpty()) {
            return ServiceResponse::success(data: $cartItems);
        }
        $productIds = $cartItems->pluck('tdms_product_id')->unique();

        $productsLatestUpdatedDateResponse = ProductService::getProductsLastUpdate(
            productIds: $productIds->toArray(),
            agentToken: app('agentType')->agent->access_token
        );

        if ($productsLatestUpdatedDateResponse->isError()) {
            return $productsLatestUpdatedDateResponse;
        }
        $productLatestUpdatedDates = $productsLatestUpdatedDateResponse->data;

        $products = Product::whereIn('tdms_product_id', $productIds);
        $fareprices = Fareprice::whereIn('tdms_product_id', $productIds)
            ->where('agent_branch', $itemType->agentBranchCode);

        $cartItems->each(function ($cartItem) use ($productLatestUpdatedDates, $products, $fareprices, $itemType) {
            $isProductAvailableInTdms = isset($productLatestUpdatedDates[$cartItem->tdms_product_id]);
            $product = (clone $products)->where('tdms_product_id', $cartItem->tdms_product_id)
                ->first();

            // Check if product is latest
            $isProductLatest = true;
            if (
                !$product ||
                $isProductAvailableInTdms == false ||
                $product->tdms_product_last_update_date != $productLatestUpdatedDates[$cartItem->tdms_product_id]
            ) {
                $product = ProductHistory::where('tdms_product_id', $cartItem->tdms_product_id)
                    ->where('version', $cartItem->product_version)
                    ->first();
                $isProductLatest = false;
            }

            if (!$product) {
                $cartItem->delete();
            } else {
                // Check if fareprice is latest
                $fareprice = (clone $fareprices)->where('tdms_product_id', $cartItem->tdms_product_id)
                    ->where('product_version', $product->version)
                    ->first();

                if (!$fareprice) {
                    $fareprice = FarepriceHistory::where('tdms_product_id', $cartItem->tdms_product_id)
                        ->where('agent_branch', $itemType->agentBranchCode)
                        ->where('product_version', $product->version)
                        ->first();
                }

                // Get latest availability
                $apiProvideId = $product->json['apiProviderId'];
                $groupFaresForAvailabilityCheck = $product->json['groupFaresForAvailabilityCheck'];
                $fareTypeId = null;
                foreach ($product->json['faresprices'] as $fare) {
                    if ($fare["productPricesDetailsId"] === $cartItem->product_price_details_id) {
                        $fareTypeId = $fare["fareTypeId"];
                        break;
                    }
                }

                $availabilityResponse = ProductService::getFarepriceAvailability(
                    agent: app('agentType')->agent,
                    productId: $cartItem->tdms_product_id,
                    productPricesDetailsId: $cartItem->product_price_details_id,
                    apiProviderId: $apiProvideId,
                    groupFaresForAvailabilityCheck: $groupFaresForAvailabilityCheck,
                    fareTypeId: $fareTypeId,
                    bookingDate: $cartItem->booking_date,
                    timeId: $cartItem->time_id,
                );
                if ($availabilityResponse->isError()) {
                    Logger::debug(
                        message: 'Error fetching availability for item: ' . $cartItem->id,
                        data: $availabilityResponse->data
                    );
                    return ServiceResponse::badRequest('Unable to get availability');
                }
                $cartItem->availability = $availabilityResponse->data;

                if ($fareprice) {
                    $productJson = $product->json;
                    $productJson['faresprices'] = $fareprice->json;
                    $product->json = $productJson;
                }

                $product->counter = 0; // Remove this later
                $cartItem->product = $product;
                $cartItem->is_availability_latest = true;
                $cartItem->is_product_latest = $isProductLatest;
                $cartItem->fareprice_branch = $fareprice ? $fareprice->agent_branch : null;
            }
        });

        return ServiceResponse::success(data: $cartItems);
    }

    public static function getQuotes($userId, bool $isPaid = false)
    {
        $quotes = Quote::where('user_id', $userId)->where('is_paid', $isPaid)->get();
        foreach ($quotes as $quote) {
            $items = CartItem::where('quote_id', $quote->id)->get();
            $productIds = $items->pluck('tdms_product_id')->unique();
            $products = Product::whereIn('tdms_product_id', $productIds)->get()->keyBy('tdms_product_id');

            $items->each(function ($item) use ($products) {
                $product = $products->get($item->tdms_product_id);
                if (!$product) {
                    $product = ProductHistory::where('tdms_product_id', $item->tdms_product_id)
                                    ->where('version', $item->product_version)
                                    ->first();
                    $product->counter = 0; // Remove this later
                }
                $item->product = $product;
            });

            $numberOfShares = ShareQuote::where('quote_id', $quote->id)
                ->where('is_declined', false)
                ->where('is_quote_deleted', false)
                ->count();

            $quote->number_of_shares = $numberOfShares;
            $quote->items = $items;
        }

        return ServiceResponse::success(data: $quotes);
    }

    public static function getMyQuotes($user, bool $isPaid = false)
    {
        $redeemerIdsForUserEmail = CartCustomerDetail::where('email', $user->email)->pluck('id')->toArray();

        if (empty($redeemerIdsForUserEmail)) {
            return ServiceResponse::success([]);
        }

        $quotes = Quote::where('is_paid', $isPaid)
            ->where('user_id', $user->uuid)
            ->get();

        foreach ($quotes as $quote) {
            $items = CartItem::userItemsByQuoteId($user->uuid, ItemType::quote($quote->id));

            $hasPrimaryRedeemer = false;
            foreach ($items->all() as $item) {
                if ($item->booking_data) {
                    $redeemers = [];
                    foreach ($item->booking_data as $data) {
                        if (isset($data['redeemers'])) {
                            $itemRedeemers = $data['redeemers'];
                            $redeemers = array_merge($redeemers, $itemRedeemers);
                        }
                    }
                    foreach ($redeemerIdsForUserEmail as $redeemerId) {
                        if (in_array($redeemerId, $redeemers)) {
                            $hasPrimaryRedeemer = true;
                            break;
                        }
                    }
                    if ($hasPrimaryRedeemer) {
                        break;
                    }
                }
            }

            if (empty($items) || !$hasPrimaryRedeemer) {
                // Remove quote from the collection
                $quotes = $quotes->reject(function ($i) use ($quote) {
                    return $i->id === $quote->id;
                });
                continue;
            }

            $productIds = $items->pluck('tdms_product_id')->unique();
            $products = Product::whereIn('tdms_product_id', $productIds)->get()->keyBy('tdms_product_id');

            $items->each(function ($item) use ($products) {
                $product = $products->get($item->tdms_product_id);
                if (!$product) {
                    $product = ProductHistory::where('tdms_product_id', $item->tdms_product_id)
                        ->where('version', $item->product_version)
                        ->first();
                }
                $product->counter = 0; // Remove this later
                $item->product = $product;
            });

            $numberOfShares = ShareQuote::where('quote_id', $quote->id)
                ->where('is_declined', false)
                ->where('is_quote_deleted', false)
                ->count();

            $quote->number_of_shares = $numberOfShares;
            $quote->items = $items;
        }

        return ServiceResponse::success(data: $quotes->values());
    }

    public static function getAllQuotes($user, bool $isPaid = false)
    {
        $redeemerIdsForUserEmail = CartCustomerDetail::where('email', $user->email)->pluck('id')->toArray();

        if (empty($redeemerIdsForUserEmail)) {
            return ServiceResponse::success([]);
        }

        $quotes = Quote::where('is_paid', $isPaid)
            ->where('user_id', $user->uuid)
            ->get();

        foreach ($quotes as $quote) {
            $items = CartItem::userItemsByQuoteId($user->uuid, ItemType::quote($quote->id));

            $hasPrimaryRedeemer = false;
            foreach ($items->all() as $item) {
                if ($item->booking_data) {
                    $redeemers = [];
                    foreach ($item->booking_data as $data) {
                        if (isset($data['redeemers'])) {
                            $itemRedeemers = $data['redeemers'];
                            $redeemers = array_merge($redeemers, $itemRedeemers);
                        }
                    }
                    foreach ($redeemerIdsForUserEmail as $redeemerId) {
                        if (in_array($redeemerId, $redeemers)) {
                            $hasPrimaryRedeemer = true;
                            break;
                        }
                    }
                    if ($hasPrimaryRedeemer) {
                        break;
                    }
                }
            }

            if (empty($items) || $hasPrimaryRedeemer) {
                // Remove quote from the collection
                $quotes = $quotes->reject(function ($i) use ($quote) {
                    return $i->id === $quote->id;
                });
                continue;
            }

            $productIds = $items->pluck('tdms_product_id')->unique();
            $products = Product::whereIn('tdms_product_id', $productIds)->get()->keyBy('tdms_product_id');

            $items->each(function ($item) use ($products) {
                $product = $products->get($item->tdms_product_id);
                if (!$product) {
                    $product = ProductHistory::where('tdms_product_id', $item->tdms_product_id)
                                ->where('version', $item->product_version)
                                ->first();
                }
                $product->counter = 0; // Remove this later
                $item->product = $product;
            });

            $numberOfShares = ShareQuote::where('quote_id', $quote->id)
                ->where('is_declined', false)
                ->where('is_quote_deleted', false)
                ->count();

            $quote->number_of_shares = $numberOfShares;
            $quote->items = $items;
        }

        return ServiceResponse::success(data: $quotes->values());
    }

    public static function getQuoteDetails($quoteId)
    {
        $quote = Quote::where('id', $quoteId)->first();
        if (!$quote) {
            return ServiceResponse::notFound();
        }

        $items = CartItem::where('quote_id', $quoteId)->get();
        $productIds = $items->pluck('tdms_product_id')->unique();
        $products = Product::whereIn('tdms_product_id', $productIds);
        $fareprices = Fareprice::whereIn('tdms_product_id', $productIds)
            ->where('agent_branch', app('agentType')->agent->branch_code);

        $items->each(function ($item) use ($products, $fareprices) {
            $product = (clone $products)->where('tdms_product_id', $item->tdms_product_id)
                                        ->where('version', $item->product_version)
                                        ->first();
            $isProductLatest = true;
            if (!$product) {
                $product = ProductHistory::where('tdms_product_id', $item->tdms_product_id)
                                        ->where('version', $item->product_version)
                                        ->first();
                $product->counter = 0;
                $isProductLatest = false;
            }

            $fareprice = (clone $fareprices)->where('tdms_product_id', $item->tdms_product_id)
                ->where('product_version', $item->product_version)
                ->first();

            $isFarepriceLatest = true;
            if (!$fareprice) {
                $fareprice = FarepriceHistory::where('tdms_product_id', $item->tdms_product_id)
                    ->where('agent_branch', app('agentType')->agent->branch_code)
                    ->where('product_version', $item->product_version)
                    ->first();
                $isFarepriceLatest = false;
            }

            if ($fareprice) {
                $productJson = $product->json;
                $productJson['faresprices'] = $fareprice->json;
                $product->json = $productJson;
            }

            $item->is_availability_latest = true;
            $item->is_product_latest = $isProductLatest;
            $item->is_fareprice_latest = $isFarepriceLatest;
            $item->product = $product;
        });

        $quote->items = $items;

        return ServiceResponse::success(data: $quote);
    }

    public static function convertExistingCartItemsToQuote($userId, $addToQuote)
    {
        try {
            $cartItems = CartItem::userCartItems($userId);
            if ($addToQuote->isNew) {
                $quote = Quote::create([
                    'user_id' => $userId,
                    'title' => $addToQuote->title,
                ]);
            } else {
                $quote = Quote::where('id', $addToQuote->quoteId)->first();
                if (!$quote) {
                    return ServiceResponse::notFound('Quote not found');
                }
            }

            foreach ($cartItems->all() as $cartItem) {
                $bookingData = $cartItem->booking_data;
                if ($bookingData) {
                    $redeemers = [];
                    foreach ($bookingData as $data) {
                        if (isset($data['redeemers'])) {
                            $cartItemRedeemers = $data['redeemers'];
                            $redeemers = array_merge($redeemers, $cartItemRedeemers);
                        }
                    }

                    CartCustomerDetail::whereIn('id', $redeemers)
                        ->where('is_primary', false)
                        ->update(['quote_id' => $quote->id]);
                }
                $cartItem->quote_id = $quote->id;
                $cartItem->save();
            }

            UserOrderCommission::updateOrCreate(
                [
                    'user_id' => $userId,
                    'quote_id' => $quote->id
                ],
                [
                    'agent_branch' => app('agentType')->agent->branch_code,
                    'percentage' => null,
                    'points_available' => null,
                ]
            );

            return ServiceResponse::success();
        } catch (Exception $e) {
            Logger::error('Failed to convert cart items to quote', $e);
            throw new ServiceException(message: 'Failed to convert cart items to quote');
        }
    }

    public static function getCartItemsByBookingReference($userId, $bookingReference)
    {
        $userOrder = UserOrder::where('booking_reference', $bookingReference)
                            ->where('user_id', $userId)
                            ->first();
        if (!$userOrder) {
            return ServiceResponse::notFound('No user order available');
        }

        $cartItems = CartItem::whereIn('id', $userOrder->cart_item_ids)->get();
        $productIds = $cartItems->pluck('tdms_product_id');
        $products = Product::whereIn('tdms_product_id', $productIds)->get()->keyBy('tdms_product_id');

        $cartItems->each(function ($cartItem) use ($products) {
            $cartItem->product = $products->get($cartItem->tdms_product_id);
        });

        return ServiceResponse::success($cartItems);
    }

    public static function updateItemBookingData($userId, int $cartItemId, int $quantity, mixed $bookingData)
    {
        $cartItem = CartItem::where('user_id', $userId)
            ->where('id', $cartItemId)
            ->first();

        if (!$cartItem) {
            return ServiceResponse::notFound(message: 'Cart item not found');
        }
        $cartItem->booking_quantity = $quantity;
        $cartItem->booking_data = $bookingData;

        $cartItem->save();
        return ServiceResponse::success('Cart item updated successfully');
    }

    public static function removeItemFromCart($userId, $cartItemId)
    {
        $cartItem = CartItem::where('user_id', $userId)->where('id', $cartItemId)->first();

        if (!$cartItem) {
            return ServiceResponse::notFound(
                message: 'Cart item not found',
            );
        }

        $cartItem->delete();

        return ServiceResponse::success();
    }

    public static function removeItemsFromCart($userId, ItemType $type)
    {
        if ($type->isCart) {
            $cartItems = CartItem::userCartItems($userId);
        } elseif ($type->isGroup) {
            $cartItems = CartItem::userGroupItems($userId, $type);
        } elseif ($type->isCartItem) {
            $cartItems = CartItem::userCartItem($userId, $type->typeId);
        } elseif ($type->isProduct) {
            $cartItems = CartItem::userCartItemsByProduct($userId, $type->typeId);
        } else {
            return ServiceResponse::badRequest(
                message: 'Invalid type',
            );
        }

        if (count($cartItems) === 0) {
            return ServiceResponse::success(
                message: 'No cart items found'
            );
        }

        foreach ($cartItems->all() as $cartItem) {
            $cartItem->delete();
        }

        $type->isCart = true;
        $commissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
            userId: $userId,
            itemType: $type
        );
        $commission = $commissionResponse->data; /** @var UserOrderCommission $commission */
        if ($commission) {
            $commission->percentage = null;
            $commission->points_available = null;
            $commission->save();
        }

        return ServiceResponse::success();
    }

    public static function removeQuote($quoteId, $userId)
    {
        $quote = Quote::where('id', $quoteId)
                        ->where('user_id', $userId)
                        ->first();

        if (!$quote) {
            return ServiceResponse::notFound(
                message: 'Quote not found',
            );
        }

        try {
            DB::beginTransaction();

            // Delete all cart items of that quote
            CartItem::where('quote_id', $quoteId)->delete();

            // Delete cart customer details of that quote
            CartCustomerDetail::where('quote_id', $quoteId)->delete();

            // Update shared quotes
            ShareQuote::where('quote_id', $quoteId)
                ->update(['is_quote_deleted' => true]);

            // Delete quote
            $quote->delete();

            // Commit the transaction
            DB::commit();

            return ServiceResponse::success();
        } catch (Exception $e) {
            DB::rollBack();

            Logger::error('Failed to remove quote.', $e);
            return ServiceResponse::badRequest(
                message: 'Failed to remove quote.'
            );
        }
    }

    public static function removeItemsFromQuote($userId, ItemType $type)
    {
        if ($type->isQuote) {
            $quoteItems = CartItem::userQuoteItems($userId, $type);
        } elseif ($type->isGroup) {
            $quoteItems = CartItem::userGroupItems($userId, $type);
        } elseif ($type->isQuoteItem) {
            $quoteItems = CartItem::userQuoteItem($userId, $type);
        } elseif ($type->isProduct) {
            $quoteItems = CartItem::userQuoteItemsByProduct($userId, $type);
        } else {
            return ServiceResponse::badRequest(
                message: 'Invalid type',
            );
        }

        if (count($quoteItems) === 0) {
            return ServiceResponse::success(
                message: 'Quote items not found',
            );
        }

        foreach ($quoteItems->all() as $quoteItem) {
            $quoteItem->delete();
        }

        $type->isQuote = true;
        $commissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
            userId: $userId,
            itemType: $type
        );
        $commission = $commissionResponse->data; /** @var UserOrderCommission $commission */
        if ($commission) {
            $commission->percentage = null;
            $commission->points_available = null;
            $commission->save();
        }

        return ServiceResponse::success();
    }

    /**
     * Set customer details with customer_index validation and synchronization.
     *
     * @param string $userId
     * @param array $data
     */
    public static function setCustomers(
        string $userId,
        array $data,
        ItemType $itemType
    ) {
        // Validate customer_index sequence
        $indices = array_column($data, 'customerIndex');
        sort($indices);

        foreach ($indices as $key => $index) {
            if ($index !== $key) {
                return ServiceResponse::badRequest(
                    message: 'Invalid index',
                );
            }
        }

        $quote = null;
        if ($itemType->isQuote) {
            $quote = Quote::where('id', $itemType->typeId)->first();
            if (is_null($quote)) {
                return ServiceResponse::notFound('Quote not found');
            }
            if ($quote->user_id !== $userId) {
                return ServiceResponse::badRequest('Permission denied');
            }
        }

        DB::transaction(function () use ($userId, $data, $itemType) {
            $existingDetails = CartCustomerDetail::where('user_id', $userId)
                ->where('is_direct_purchase', $itemType->isDirect)
                ->when($itemType->isQuote, fn ($query) => $query->where('quote_id', $itemType->isQuote))
                ->when($itemType->isCart, fn ($query) => $query->whereNull('user_order_id')->whereNull('quote_id'))
                ->get()->keyBy('customer_index');

            $newIndices = array_column($data, 'customerIndex');

            // Update or create records
            foreach ($data as $detail) {
                $customerData = [
                    'user_id' => $userId,
                    'title' => $detail['title'] ?? null,
                    'first_name' => $detail['firstName'],
                    'last_name' => $detail['lastName'],
                    'date_of_birth' => $detail['dateOfBirth'],
                    'email' => $detail['email'],
                    'country_code' => $detail['countryCode'] ?? "036",
                    'postal_code' => $detail['postalCode'] ?? null,
                    'customer_index' => $detail['customerIndex'],
                    'phone_number' => $detail['phoneNumber'],
                    'is_direct_purchase' => $itemType->isDirect,
                ];

                // Only add country_code if it exists in $detail
                if (isset($detail['countryCode'])) {
                    $customerData['country_code'] = $detail['countryCode'];
                }
                $columnsToMatch = [
                    'user_id' => $userId,
                    'quote_id' => null,
                    'customer_index' => $detail['customerIndex'],
                    'user_order_id' => null,
                    'is_direct_purchase' => $itemType->isDirect
                ];
                if ($itemType->isQuote) {
                    $columnsToMatch['quote_id'] = $itemType->typeId;
                    $customerData['quote_id'] = $itemType->typeId;
                }

                CartCustomerDetail::updateOrCreate(
                    $columnsToMatch,
                    $customerData
                );
            }

            // Delete records that are in the database but not in the input
            Logger::debug($existingDetails->keys());
            $indicesToDelete = $existingDetails->keys()->diff($newIndices);
            Logger::debug($indicesToDelete);
            if ($indicesToDelete->isNotEmpty()) {
                CartCustomerDetail::where('user_id', $userId)
                    ->where('is_direct_purchase', $itemType->isDirect)
                    ->whereIn('customer_index', $indicesToDelete)
                    ->when($itemType->isQuote, fn ($query) => $query->where('quote_id', $itemType->typeId))
                    ->when($itemType->isCart, fn ($query) => $query->whereNull('user_order_id'))
                    ->delete();
            }
        });

        return ServiceResponse::success();
    }

    public static function getCustomers(string $userId, ItemType $itemType)
    {
        $customerDetails = CartCustomerDetail::where('user_id', $userId)
        ->where('is_direct_purchase', $itemType->isDirect)
        ->when($itemType->isQuote, fn ($query) => $query->where('quote_id', $itemType->typeId))
        ->when($itemType->isCart, fn ($query) => $query->whereNull('user_order_id')->whereNull('quote_id'))
        ->get();
        return ServiceResponse::success(data: $customerDetails);
    }

    public static function getUserCartItemsWithProductDetails($userId)
    {
        $cartItems = CartItem::where('user_id', $userId)
                                ->select('id', 'tdms_product_id', 'product_price_details_id', 'booking_date', 'time_id')
                                ->get();

        $itemsWithProductDetails = [];
        if (!$cartItems) {
            return $itemsWithProductDetails;
        }

        foreach ($cartItems as $item) {
            $productPriceAvailability = ProductPriceAvailability::where('tdms_product_id', $item->tdms_product_id)
                                            ->where('product_price_details_id', $item->product_price_details_id)
                                            ->first();
            $item = $item->toArray();
            $tdmsProductId = $item['tdms_product_id'];

            $isLatestVersion = true;
            $productDetailsResponse = Product::where('tdms_product_id', $tdmsProductId)
                            ->where('version', $item['product_version'])
                            ->first();
            if (!$productDetailsResponse) {
                $isLatestVersion = false;
                $productDetailsResponse = ProductHistory::where('tdms_product_id', $tdmsProductId)
                                                ->where('version', $item['product_version'])
                                                ->first();
            }
            $item['version'] = $productDetailsResponse->version;
            $item['counter'] = $productDetailsResponse->counter;
            $item['details'] = $productDetailsResponse->json;
            $item['details']['availability'] = $productPriceAvailability
                                            ? $productPriceAvailability->toArray()
                                            : [];
            $item['is_latest_version'] = $isLatestVersion;

            $itemsWithProductDetails[] = $item;
        }

        return $itemsWithProductDetails;
    }

    public static function getCartItemProductAvailability($agentToken, $item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;
        $timeId = $item->time_id;
        $startDate = $item->booking_date;

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt(
            $curl,
            CURLOPT_URL,
            "{$requestUrl}/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/1"
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (!$result || isset($result['errors'])) {
            return null;
        }
        return $result[0];
    }

    public static function getCartItemBookingDetails($agentToken, $item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;


        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/bookingdetails/{$productPricesDetailsId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (isset($result['errors'])) {
            return null;
        }
        return $result;
    }

    public static function cleanCartItems(
        string $userId,
        $bookingReference,
        $cartItemIds,
        $requestData,
        $responseData,
        $intent,
        $paymentGateway,
        $userAgentId,
        $quoteId = null
    ) {
        DB::transaction(function () use (
            $bookingReference,
            $cartItemIds,
            $responseData,
            $requestData,
            $intent,
            $userId,
            $paymentGateway,
            $userAgentId,
            $quoteId
        ) {
            //TODO: remove customers
            $userOrder = UserOrder::create([
                'booking_reference' => $bookingReference,
                'cart_item_ids' => $cartItemIds,
                'intent' => $intent,
                'user_id' => $userId,
                'request_data' => $requestData,
                'response_data' => $responseData,
                'payment_gateway' => $paymentGateway,
                'user_agent_id' => $userAgentId,
                'order_id' => $responseData['id'],
                'applied_discount' => config('vars.discount_percentage')
            ]);

            if ($intent === 'email-quote') { // emailing quote should remove items from cart
                CartItem::whereIn('id', $cartItemIds)->update(['user_order_id' => $userOrder->id]);
                CartCustomerDetail::where('user_id', $userId)
                                ->when(!is_null($quoteId), fn ($query) => $query->where('quote_id', $quoteId))
                                ->when(is_null($quoteId), fn ($query) => $query->whereNull('quote_id'))
                                ->update(['user_order_id' => $userOrder->id]);
                UserOrderCommission::where('user_id', $userId)
                                ->when(is_null($quoteId), fn ($query) => $query->where('is_cart', true))
                                ->update(['user_order_id', $userOrder->id]);
            }
        });
    }

    public static function getCustomerOrder($userId, $bookingReference)
    {
        $userOrder = UserOrder::where('user_id', $userId)
                              ->where('booking_reference', $bookingReference)
                              ->first();

        if (!$userOrder) {
            return ServiceResponse::notFound('No user order available');
        }

        $data = [
            "userId" => $userOrder->user_id,
            "bookingReference" => $userOrder->booking_reference,
            "intent" => $userOrder->intent,
            "cart_item_ids" => $userOrder->cart_item_ids,
            "paymentGateway" => $userOrder->payment_gateway,
            "is_paid" => $userOrder->is_paid
        ];

        return ServiceResponse::success($data);
    }

    public static function completeBooking($bookingReference)
    {
        $userOrder = UserOrder::where('booking_reference', $bookingReference)->first();
        if (!$userOrder) {
            return ServiceResponse::notFound('User order not found');
        }

        $getAgentResponse = UserAgentService::getUserAgentById($userOrder->user_agent_id);
        if ($getAgentResponse->isError()) {
            return $getAgentResponse;
        }

        $agent = $getAgentResponse->data;

        // checkIfCustomerOrderStatusIsOrder checks order status of provided bookingReference
        // If in Order status, we do not need to convert quote as it is already an order
        // If not in Order status, we need to convert quote to order
        $checkIfOrderStatusIsOrder = TdmsService::checkIfCustomerOrderStatusIsOrder(
            agentToken: $agent->access_token,
            orderId: $userOrder->order_id
        );

        if ($checkIfOrderStatusIsOrder->responseCode == 404) {
            $convertQuoteToOrderResponse = TdmsService::convertQuoteToOrder(
                $agent->access_token,
                $bookingReference
            );

            if ($convertQuoteToOrderResponse->isError()) {
                return $convertQuoteToOrderResponse;
            }
        } elseif ($checkIfOrderStatusIsOrder->responseCode == 400) {
            return $checkIfOrderStatusIsOrder;
        }

        try {
            BookingService::completeOrder(
                bookingReference: $bookingReference,
            );
            return ServiceResponse::success(data: $userOrder);
        } catch (Exception $e) {
            Logger::error(message: 'Order completion failed', exception: $e);
            return ServiceResponse::badRequest(message: 'Order completion failed');
        }
    }

    public static function validateProductAvailability($agentToken, $cartItems)
    {
        $errorData = [];
        foreach ($cartItems as $cartItem) {
            $productAvailabilitiesResponse = ProductService::getProductAvailabilitiesFromApi(
                agentToken: $agentToken,
                productPricesDetailsId: $cartItem->product_price_details_id,
                timeId: $cartItem->booking_data['timeId'],
                startDate: $cartItem->start_date,
                days: $cartItem->days
            );

            if ($productAvailabilitiesResponse->isError()) {
                return ServiceResponse::notFound(
                    message: 'Product availability not found',
                );
            }

            $productAvailabilities = $productAvailabilitiesResponse->data;
            $productAvailability = $productAvailabilities[$cartItem->selected_index];

            if ($productAvailability['NumAvailable'] < $cartItem->booking_quantity) {
                $errorData[] = [
                    $cartItem->product_price_details_id => [
                        "availableQty" => $productAvailability['NumAvailable'],
                        "requestedQty" => $cartItem->booking_quantity
                    ]
                ];
            }

            if ($errorData) {
                return ServiceResponse::badRequest(
                    message: "Requested quantity not available",
                    data: $errorData
                );
            } else {
                return ServiceResponse::success();
            }
        }
    }

    public static function cleanDirectPurchase($userId)
    {
        // Remove items and customers that were addded for direct purchase but are not associated to any order.
        try {
            $items = CartItem::userDirectPurchaseItems($userId);
            $items->each->delete();
        } catch (Exception $e) {
            Logger::error('Failed to clean direct purchase items', $e);
            throw new ServiceException(message: 'Failed to clean direct purchase items');
        }

        return ServiceResponse::success();
    }

    public static function updateWithLatestDetails($user, $itemType): ServiceResponse
    {
        $agentType = app('agentType');
        $items = CartItem::userItems($user->uuid, $itemType)->all();
        if (empty($items)) {
            return ServiceResponse::notFound();
        }

        $responseData = [];
        foreach ($items as $item) {
            $cacheResponse = self::cacheProductV2($item->tdms_product_id, $agentType->agent);
            if ($cacheResponse->isError()) {
                $itemStatus = 'Expired';
                continue;
            } else {
                $product = Product::where('tdms_product_id', $item->tdms_product_id)
                    ->first();

                $item->product_version = $product->version;
                $item->save();
                $itemStatus = 'Updated';
            }

            $responseData[] = [
                'itemId' => $item->id,
                'status' => $itemStatus,
            ];
        }

        return ServiceResponse::success(data: $responseData);
    }
}
