<?php

namespace App\Services;

use App\Models\ShareQuote;
use App\Models\UserOrderCommission;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\DTOs\AddToQuote;
use App\DTOs\ItemType;
use App\DTOs\OrderItemRequestData;
use App\Jobs\ShareQuoteJob;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Fareprice;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Str;

class CartItemServiceV2
{
    public static function buildOrderItemRequestData(
        string|null $userId,
        int $tdmsProductId,
        array $productPricesDetails,
        $itemType = null,
        $isDryRun = false
    ): ServiceResponse|HttpResponse {
        $agentType = app('agentType');
        $agent = $agentType->agent;

        $latestCachedProductResponse = CartItemService::cacheProductV2($tdmsProductId, $agent);
        if ($latestCachedProductResponse->isError()) {
            return $latestCachedProductResponse;
        }

        $latestCachedProduct = Product::where('tdms_product_id', $tdmsProductId)
            ->first();

        $latestCachedFareprice = Fareprice::where('tdms_product_id', $tdmsProductId)
            ->where('agent_branch', $agent->branch_code)
            ->where('product_version', $latestCachedProduct->version)
            ->first();

        $result = [];
        $existingUserCartItems = CartItem::userItems($userId, $itemType);
        foreach ($productPricesDetails as $productPriceDetails) {
            $productPriceDetailsId = $productPriceDetails['productPricesDetailsId'];
            $apiProviderId = $latestCachedProduct->json['apiProviderId'];
            $groupFaresForAvailabilityCheck = $latestCachedProduct->json['groupFaresForAvailabilityCheck'];

            // Find fareTypeId for the given productPricesDetailsId
            $farePrices = $latestCachedFareprice->json;
            $farePrice = null;
            foreach ($farePrices as $fare) {
                if ((string) $fare["productPricesDetailsId"] === (string) $productPriceDetailsId) {
                    $farePrice = $fare;
                    break;
                }
            }
            $fareTypeId = $farePrice['fareTypeId'];
            $numPax = (int) $farePrice['numPax'];

            $quantityDetails = $productPriceDetails['quantityDetails'];

            $bookingDates = array_map(function ($detail) {
                return $detail['bookingDate'];
            }, $quantityDetails);

            if (!$itemType->isDry && !$itemType->forDiscount) {
                $sameItemsOnSameDate = $existingUserCartItems->where('product_price_details_id', $productPriceDetailsId)
                                    ->whereIn('booking_date', $bookingDates);

                if ($sameItemsOnSameDate->isNotEmpty() && !$isDryRun) {
                    $productDetailsOfSameItem = Product::where(
                        'tdms_product_id',
                        (clone $sameItemsOnSameDate)->first()->tdms_product_id
                    )->first();

                    $isAccommodationProduct = $productDetailsOfSameItem->json['productClass'] == 'A';

                    if (!$isAccommodationProduct) {
                        $sameItems = [];
                        foreach ($sameItemsOnSameDate->all() as $sameItemOnSameDate) {
                            $sameItems[] = [
                                'cartItemId' => $sameItemOnSameDate->id,
                                'productPriceDetailsId' => $productPriceDetailsId,
                                'bookingDate' => $sameItemOnSameDate->booking_date,
                            ];
                        }
                        return ServiceResponse::badRequest(
                            message: 'Item already exists',
                            data: $sameItems
                        );
                    }
                }
            }

            if (!$itemType->forDiscount) {
                $bookingDetailsResponse = ProductService::getBookingDetails(
                    agentToken: $agent->access_token,
                    productPricesDetailsId: $productPriceDetails['productPricesDetailsId'],
                );

                if ($bookingDetailsResponse->isError()) {
                    if ($bookingDetailsResponse->responseCode == 404) {
                        return ServiceResponse::notFound('Booking details not found');
                    }

                    $errorMessage = 'Failed to load booking details';
                    Logger::error("{$errorMessage}: {$bookingDetailsResponse->message}");
                    throw new ServiceException(message: $errorMessage);
                }
                $bookingDetails = $bookingDetailsResponse->data;
            }

            foreach ($quantityDetails as $details) {
                $quantityIndex = 0;
                $bookingData = [];
                if (isset($details['bookingData'])) {
                    foreach ($details['bookingData'] as $data) {
                        $quantityIndex = isset($data['quantityIndex']) ? $data['quantityIndex'] : ++$quantityIndex;
                        $timeId = $data['timeId'];
                        $commences = $data['commences'] ?? null;
                        $optionalData = $data['optionalData'] ?? [];
                        $pickupId = $data['pickupId'] ?? null;
                        $pickupLocation = $data['pickupLocation'] ?? null;
                        $redeemers = array_slice($data['redeemers'] ?? [], 0, $numPax);
                        if (
                            $numPax > 1 &&
                            count($redeemers) > 0 &&
                            count($redeemers) < $numPax
                        ) {
                            while (count($redeemers) < $numPax) {
                                $redeemers[] = $redeemers[0];
                            }
                        }

                        if (count($redeemers) == 0) {
                            $bookingData[] = [
                                "quantityIndex" => $quantityIndex,
                                "timeId" => $timeId,
                                "commences" => $commences,
                                "pickupId" => $pickupId,
                                "pickupLocation" => $pickupLocation,
                                "optionalData" => $optionalData,
                                "redeemers" => []
                            ];
                            continue;
                        }

                        foreach ($redeemers as $redeemer) {
                            $bookingData[] = [
                                "quantityIndex" => $quantityIndex,
                                "timeId" => $timeId,
                                "commences" => $commences,
                                "pickupId" => $pickupId,
                                "pickupLocation" => $pickupLocation,
                                "optionalData" => $optionalData,
                                "redeemers" => [$redeemer]
                            ];
                        }
                    }
                } else {
                    $quantity = $details['quantity'];
                    $noOfBookingData = intdiv((int) $quantity, $numPax);
                    $timeId = $details['timeId'] ?? '0';
                    $commences = $details['commences'] ?? null;
                    $optionalData = $details['bookingData']['optionalData'] ?? [];
                    $bookingData = $details['bookingData'] ?? [];
                    for ($i = 1; $i <= $noOfBookingData; $i++) {
                        $bookingData[] = [
                            "quantityIndex" => ++$quantityIndex,
                            "timeId" => $timeId,
                            "commences" => $commences,
                            "optionalData" => $optionalData
                        ];
                    }
                }
                $quantity = $quantityIndex * $numPax;
                $bookingDate = $details['bookingDate'];

                if (
                    isset($fare['fareQtyRestrictions'])
                    && $quantity % (int) $fare['fareQtyRestrictions'] != 0
                ) {
                    return ServiceResponse::badRequest(
                        message: 'Quantity must be multiple of ' . $fare['fareQtyRestrictions']
                    );
                }

                $productAvailabilitiesResponse = ProductService::getFarepriceAvailability(
                    agent: $agentType->agent,
                    productId: $tdmsProductId,
                    productPricesDetailsId: $productPriceDetailsId,
                    apiProviderId: $apiProviderId,
                    groupFaresForAvailabilityCheck: $groupFaresForAvailabilityCheck,
                    fareTypeId: $fareTypeId,
                    bookingDate: $bookingDate,
                    timeId: $timeId
                );
                if ($productAvailabilitiesResponse->isError()) {
                    return ServiceResponse::badRequest('Availability not found');
                }

                $productAvailabilities = $productAvailabilitiesResponse->data;

                $result[] = new OrderItemRequestData(
                    product: $latestCachedProduct,
                    fareprices: $latestCachedFareprice,
                    productPriceDetailsId: $productPriceDetailsId,
                    productLastUpdate: Carbon::now(),
                    productBookingDetails: $bookingDetails ?? [],
                    productAvailabilities: $productAvailabilities,
                    bookingData: $bookingData,
                    quantity: $quantity,
                    timeId: $timeId,
                    commences: $commences
                );
            }
        }

        return ServiceResponse::success(data: $result);
    }

    public static function buildCartItemsData(
        string|null $userId,
        int $tdmsProductId,
        int|null $productVersion,
        string $startDate,
        int $days,
        array $selectedAvailableIndices,
        Carbon $availabilityLastUpdatedAt,
        ItemType $itemType,
        ?Quote $quote,
        array $orderItemsData
    ): array {
        $cartItemsData = [];

        $groupId = Str::uuid();
        $selectedItemIndex = 0;
        foreach ($orderItemsData as $orderItemData) {
            $availability = $orderItemData->productAvailabilities;
            $bookingData = $orderItemData->bookingData;
            $bookingDetails = $orderItemData->productBookingDetails;

            // Consecutive days are grouped into same group_id
            if ($selectedItemIndex !== $selectedAvailableIndices[0]) {
                $groupId = Str::uuid();
                $selectedItemIndex = $selectedAvailableIndices[0];
            }
            $selectedItemIndex++;

            $newCartData = [
                'user_id' => $userId,
                'tdms_product_id' => $tdmsProductId,
                'group_id' => $groupId,
                'product_version' => $productVersion,
                'product_price_details_id' => $orderItemData->productPriceDetailsId,
                'booking_date' => BaseService::stringToDate($availability['BookingDate']),
                'booking_quantity' => $orderItemData->quantity,
                'start_date' => BaseService::stringToDate($startDate),
                'days' => $days,
                'time_id' => $orderItemData->timeId,
                'commences' => $orderItemData->commences,
                'availability' => $availability,
                'availability_last_updated_at' => $availabilityLastUpdatedAt,
                'booking_details' => $bookingDetails,
                'selected_index' => array_shift($selectedAvailableIndices),
                'booking_data' => $bookingData,
                'is_direct_purchase' => $itemType->isDirect,
                'session_id' => $itemType->isSession ? $itemType->typeId : null
            ];
            if (!is_null($quote)) {
                $newCartData['quote_id'] = $quote->id;
            }
            array_push($cartItemsData, $newCartData);
        }
        return $cartItemsData;
    }

    public static function saveItems(
        string|null $userId,
        int $tdmsProductId,
        string $startDate,
        int $days,
        array $selectedAvailableIndices,
        ItemType $itemType,
        array $productPricesDetails,
        AddToQuote|null $addToQuote = null,
        bool $isDryRun = false,
    ) {
        try {
            $quote = null;
            if (!is_null($addToQuote)) {
                if ($addToQuote->isNew) {
                    $quote = Quote::create([
                        'user_id' => $userId,
                        'title' => $addToQuote->title
                    ]);
                } else {
                    $quote = Quote::where('id', $addToQuote->quoteId)->first();
                }
                $itemType->typeId = $quote->id;
            }
            $buildRequestDataResponse = self::buildOrderItemRequestData(
                userId: $userId,
                tdmsProductId: $tdmsProductId,
                productPricesDetails: $productPricesDetails,
                itemType: $itemType,
                isDryRun: $isDryRun
            );
            if (!$buildRequestDataResponse->isSuccess()) {
                return $buildRequestDataResponse;
            }
            $orderItemsData = $buildRequestDataResponse->data;

            $now = Carbon::now();
            $cartItems = [];
            $cartItems = DB::transaction(function () use (
                $tdmsProductId,
                $now,
                $userId,
                $startDate,
                $days,
                $selectedAvailableIndices,
                $orderItemsData,
                $cartItems,
                $quote,
                $itemType,
            ) {
                $cachedProduct = Product::where('tdms_product_id', $tdmsProductId)
                                ->orderBy('version', 'desc')
                                ->first();
                $productVersion = $cachedProduct->version;

                $cartItemsData = self::buildCartItemsData(
                    userId: $userId,
                    tdmsProductId: $tdmsProductId,
                    productVersion: $productVersion,
                    startDate: $startDate,
                    days: $days,
                    selectedAvailableIndices: $selectedAvailableIndices,
                    availabilityLastUpdatedAt: $now,
                    itemType: $itemType,
                    quote: $quote,
                    orderItemsData: $orderItemsData
                );

                if ($itemType->isDry || $itemType->forDiscount) {
                    foreach ($cartItemsData as &$cartItemData) {
                        $cartItemData['product'] = $cachedProduct->toArray();
                    }
                    return $cartItemsData;
                } elseif ($userId) {
                    $commissionResponse = UserOrderCommissionService::getUserOrderCommissionByItemType(
                        $userId,
                        $itemType
                    );
                    if ($commissionResponse->isSuccess()) {
                        $commission = $commissionResponse->data; /** @var UserOrderCommission $commission */
                        $commission->percentage = null;
                        $commission->points_available = null;
                        $commission->save();
                    }
                }

                foreach ($cartItemsData as $cartItemData) {
                    $newCartItem = CartItem::create($cartItemData);
                    $cartItemWithProduct = CartItem::with('product')
                        ->where('id', $newCartItem->id)
                        ->first();
                    array_push($cartItems, $cartItemWithProduct);
                }

                return $cartItems;
            });

            return ServiceResponse::success(data: $cartItems);
        } catch (ServiceException $e) {
            Logger::error('Failed to save cart items', $e);
            return $e->toServiceResponse();
        }
    }

    public static function updateItemBookingDataV2($data)
    {
        try {
            $cartItemIds = array_map(function ($item) {
                return $item['cartItemId'];
            }, $data);

            $cartItems = CartItem::whereIn('id', $cartItemIds)->get();

            $isQuantityChanged = false;
            DB::beginTransaction();
            foreach ($data as $item) {
                $cartItem = (clone $cartItems)->where('id', $item['cartItemId'])->first();

                $bookingDatas = [];
                foreach ($item['bookingData'] as $bookingData) {
                    $bookingData['optionalData'] = $bookingData['optionalData'] ?? [];
                    $bookingDatas[] = $bookingData;
                }

                if ($cartItem->booking_quantity != $item['quantity']) {
                    $isQuantityChanged = true;
                }

                $cartItem->update([
                    "booking_quantity" => $item['quantity'],
                    "booking_data" => $bookingDatas
                ]);
            };
            DB::commit();

            return ServiceResponse::success(
                message: 'Cart items updated successfully',
                data: ['isQuantityChanged' => $isQuantityChanged]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Logger::error('Failed to update item booking data V2', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'action' => 'update_booking_data_v2_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to update booking data: ' . $e->getMessage()
            );
        }
    }

    public static function updateItemBookingData($data)
    {
        try {
            $cartItemIds = array_map(function ($item) {
                return $item['cartItemId'];
            }, $data);

            $cartItems = CartItem::whereIn('id', $cartItemIds)->get();

            $isQuantityChanged = false;
            DB::beginTransaction();
            foreach ($data as $item) {
                $cartItem = (clone $cartItems)->where('id', $item['cartItemId'])->first();

                $bookingDatas = [];
                $numpax = $item['quantity'] / count($item['bookingData']);
                $quantityIndex = 1;
                foreach ($item['bookingData'] as $bookingData) {
                    $redeemers = array_slice($bookingData['redeemers'] ?? [], 0, $numpax);
                    if (isset($bookingData['redeemers']) && !empty($bookingData['redeemers'])) {
                        if ($numpax > 1 && count($redeemers) < $numpax) {
                            while (count($redeemers) < $numpax) {
                                $redeemers[] = $redeemers[0];
                            }
                        }
                        foreach ($redeemers as $redeemer) {
                            $bookingData['quantityIndex'] = $quantityIndex;
                            $bookingData['redeemers'] = [$redeemer];
                            $bookingData['optionalData'] = $bookingData['optionalData'] ?? [];
                            $bookingDatas[] = $bookingData;
                        }
                    } else {
                        $bookingData['quantityIndex'] = $quantityIndex;
                        $bookingData['optionalData'] = $bookingData['optionalData'] ?? [];
                        $bookingData['redeemers'] = $redeemers;
                        $bookingDatas[] = $bookingData;
                    }

                    $quantityIndex++;
                }

                if ($cartItem->booking_quantity != $item['quantity']) {
                    $isQuantityChanged = true;
                }

                $cartItem->update([
                    "booking_quantity" => $item['quantity'],
                    "booking_data" => $bookingDatas
                ]);
            };
            DB::commit();

            return ServiceResponse::success(
                message: 'Cart items updated successfully',
                data: ['isQuantityChanged' => $isQuantityChanged]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Logger::error('Failed to update item booking data', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'action' => 'update_booking_data_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to update booking data: ' . $e->getMessage()
            );
        }
    }

    public static function shareQuote($sharedByUserId, $sharedToEmail, $quoteId, $agentType)
    {
        try {
            $quote = Quote::where('user_id', $sharedByUserId)
                ->where('id', $quoteId)
                ->first();
            if (!$quote) {
                return ServiceResponse::notFound(
                    message: 'Quote not found',
                    data: ['quoteId' => $quoteId]
                );
            }

            $shareQuote = ShareQuote::updateOrCreate(
                [
                    'shared_by_user_id' => $sharedByUserId,
                    'shared_to_email' => $sharedToEmail,
                    'quote_id' => $quote->id
                ],
                [
                    'is_accepted' => false,
                    'is_declined' => false,
                ]
            );

            ShareQuoteJob::dispatch(
                shareQuote: $shareQuote,
                agentType: $agentType,
                itemType: ItemType::quote($quoteId)
            );

            return ServiceResponse::success(data: $shareQuote, message: 'Invitation sent');
        } catch (\Exception $e) {
            Logger::error('Failed to share quote', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $sharedByUserId,
                'quote_id' => $quoteId,
                'action' => 'share_quote_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to share quote: ' . $e->getMessage()
            );
        }
    }

    public static function getQuotesSharedToMe($user)
    {
        try {
            $sharedQuotes = ShareQuote::query()
                ->where('shared_to_email', $user->email)
                ->where('is_accepted', false)
                ->where('is_declined', false)
                ->where('is_quote_deleted', false)
                ->select(
                    DB::raw('MIN(id) as id'),
                    'quote_id',
                    DB::raw('MIN(shared_by_user_id) as shared_by_user_id')
                )
                ->groupBy('quote_id');

            $sharedQuoteIds = (clone $sharedQuotes)->pluck('quote_id')
                ->toArray();

            $quotesQuery = Quote::query()->whereIn('id', $sharedQuoteIds);

            $result = [];
            foreach ($sharedQuotes->get() as $sharedQuote) {
                $sharedByUserEmail = User::where('uuid', $sharedQuote->shared_by_user_id)->first()->email;
                $quote = (clone $quotesQuery)->where('id', $sharedQuote->quote_id)
                    ->select(['id', 'title', 'created_at', 'updated_at'])
                    ->first();
                if (!$quote) {
                    $sharedQuote->is_quote_deleted = true;
                    $sharedQuote->save();
                    continue;
                }
                $noOfItems = CartItem::query()->where('quote_id', $quote->id)
                    ->count();

                $quote->quote_share_id = $sharedQuote->id;
                $quote->no_of_items = $noOfItems;
                $quote->shared_by = $sharedByUserEmail;

                $quoteItems = CartItem::query()->where('quote_id', $quote->id)
                    ->with('product')
                    ->get();

                $totalPrice = BookingService::getTotalChargeAmount(cartItems: $quoteItems);
                $quote->total_rrp = $totalPrice;
                $result[] = $quote;
            }

            return ServiceResponse::success(data: $result);
        } catch (\Exception $e) {
            Logger::error('Failed to get quotes shared to user', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $user->uuid,
                'action' => 'get_shared_quotes_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to get shared quotes: ' . $e->getMessage()
            );
        }
    }

    public static function getQuoteSharedUsers($quoteId)
    {
        try {
            $quoteSharedUsers = ShareQuote::query()
                ->select([
                    'share_quotes.shared_to_email as email',
                    'cart_customer_details.first_name as first_name',
                    'cart_customer_details.last_name as last_name',
                ])
                ->leftJoin('cart_customer_details', function ($join) use ($quoteId) {
                    $join->on('cart_customer_details.email', '=', 'share_quotes.shared_to_email')
                        ->where('cart_customer_details.quote_id', '=', $quoteId);
                })
                ->where('share_quotes.quote_id', $quoteId)
                ->where('share_quotes.is_quote_deleted', false)
                ->where('share_quotes.is_declined', false)
                ->get()
                ->map(function ($item) {
                    if ($item->first_name && $item->last_name) {
                        $name = $item->first_name . ' ' . $item->last_name;
                    } else {
                        $name = null;
                    }
                    return [
                        'email' => $item->email,
                        'name' => $name,
                    ];
                })
                ->toArray();

            return ServiceResponse::success(data: $quoteSharedUsers);
        } catch (\Exception $e) {
            Logger::error('Failed to get quote shared users', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'quote_id' => $quoteId,
                'action' => 'get_quote_shared_users_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to get quote shared users: ' . $e->getMessage()
            );
        }
    }

    public static function acceptQuoteInvite($user, $quoteShareId)
    {
        try {
            $quoteShareInstance = ShareQuote::where('id', $quoteShareId)
                ->first();
            if (!$quoteShareInstance) {
                return ServiceResponse::notFound(
                    message: 'Shared quote not found',
                    data: ['quoteShareId' => $quoteShareId]
                );
            }
            if ($quoteShareInstance->shared_to_email != $user->email) {
                return ServiceResponse::unauthorized(
                    message: 'You are not authorized to accept this quote',
                );
            }
            if ($quoteShareInstance->is_accepted) {
                return ServiceResponse::badRequest(
                    message: 'You have already accepted this quote',
                );
            }
            if ($quoteShareInstance->is_declined) {
                return ServiceResponse::badRequest(
                    message: 'You have already declined this quote',
                );
            }

            $quote = Quote::where('id', $quoteShareInstance->quote_id)
                ->first();

            if (!$quote) {
                return ServiceResponse::notFound(message: 'Quote not found');
            }

            DB::beginTransaction();
            $newQuote = Quote::create([
                'user_id' => $user->uuid,
                'title' => $quote->title,
                'shared_by_email' => User::where('uuid', $quoteShareInstance->shared_by_user_id)->first()->email
            ]);

            $cartItems = CartItem::where('quote_id', $quote->id)
                ->get();

            foreach ($cartItems as $cartItem) {
                $cartItemData = $cartItem->toArray();
                unset($cartItemData['id']);

                foreach ($cartItemData['booking_data'] as &$bookingData) {
                    unset($bookingData['redeemers']);
                    $bookingData['optionalData'] = [];
                }

                $cartItemData['user_id'] = $user->uuid;
                $cartItemData['quote_id'] = $newQuote->id;
                CartItem::create($cartItemData);
            }

            $quoteShareInstance->is_accepted = true;
            $quoteShareInstance->save();
            DB::commit();

            return ServiceResponse::success(data: $newQuote, message: 'Quote accepted');
        } catch (\Exception $e) {
            DB::rollBack();
            Logger::error('Failed to accept quote invite', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $user->uuid,
                'quote_share_id' => $quoteShareId,
                'action' => 'accept_quote_invite_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to accept quote invite: ' . $e->getMessage()
            );
        }
    }

    public static function rejectQuoteInvite($user, $quoteShareId)
    {
        try {
            $quoteShareInstance = ShareQuote::where('id', $quoteShareId)
                ->first();
            if (!$quoteShareInstance) {
                return ServiceResponse::notFound(
                    message: 'Shared quote not found',
                    data: ['quoteShareId' => $quoteShareId]
                );
            }

            $quoteShareInstance->is_declined = true;
            $quoteShareInstance->save();

            return ServiceResponse::success();
        } catch (\Exception $e) {
            Logger::error('Failed to reject quote invite', $e, data: [
                'log_file' => config('logging.log_files.errors'),
                'user_id' => $user->uuid,
                'quote_share_id' => $quoteShareId,
                'action' => 'reject_quote_invite_failed',
            ]);
            throw new ServiceException(
                message: 'Failed to reject quote invite: ' . $e->getMessage()
            );
        }
    }
}
