<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\DTOs\AddToQuote;
use App\DTOs\ItemType;
use App\DTOs\OrderItemRequestData;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Str;

class CartItemServiceV2
{
    public static function buildOrderItemRequestData(
        string $userId,
        int $tdmsProductId,
        array $productPricesDetails,
        $itemType = null
    ): ServiceResponse {
        $userAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);
        $userAgent = $userAgentResponse->data;
        $defaultAgentAccessToken = $userAgent->access_token;

        $productDetailsResponse = ProductService::getProductDetails($defaultAgentAccessToken, $tdmsProductId);
        if (!$productDetailsResponse) {
            return ServiceResponse::notFound(
                message: 'Product not found',
            );
        }
        $productDetails = $productDetailsResponse['results'][0];

        $productLastUpdate = ProductService::getProductsLastUpdateFromApi(
            $defaultAgentAccessToken,
            [$tdmsProductId],
        );
        if (!$productLastUpdate) {
            throw new ServiceException(
                message: 'Product last update not found',
            );
        }
        $productLastUpdate = $productLastUpdate[$tdmsProductId];

        CartItemService::cacheProduct(
            product: $productDetails,
            checkTime: $productLastUpdate,
        );

        $result = [];
        $existingUserCartItems = CartItem::userCartItems($userId);
        if ($itemType->isDirect) {
            $existingUserCartItems = CartItem::userDirectPurchaseItems($userId);
        }
        foreach ($productPricesDetails as $productPriceDetails) {
            $productPriceDetailsId = $productPriceDetails['productPricesDetailsId'];
            $quantityDetails = $productPriceDetails['quantityDetails'];

            $bookingDates = array_map(function ($detail) {
                return $detail['bookingDate'];
            }, $quantityDetails);

            $sameItemsOnSameDate = $existingUserCartItems->where('product_price_details_id', $productPriceDetailsId)
                                ->whereIn('booking_date', $bookingDates);

            if ($sameItemsOnSameDate->isNotEmpty()) {
                $productDetails = Product::where('tdms_product_id', $sameItemsOnSameDate->first()->tdms_product_id)
                                        ->first();

                $isAccommodationProduct = $productDetails->json['productClass'] == 'A';

                if (!$isAccommodationProduct) {
                    $sameItems = [];
                    foreach ($sameItemsOnSameDate as $sameItemOnSameDate) {
                        $sameItems[] = [
                            'cartItemId' => $sameItemOnSameDate->id,
                            'productPriceDetailsId' => $productPriceDetailsId,
                            'bookingDate' => $sameItemOnSameDate->booking_date,
                        ];
                    }
                    return ServiceResponse::badRequest(
                        message: 'Item already exists in cart',
                        data: $sameItems
                    );
                }
            }


            $bookingDetailsResponse = ProductService::getBookingDetails(
                agentToken: $defaultAgentAccessToken,
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

            foreach ($quantityDetails as $details) {
                if ($itemType && $itemType->isDirect) {
                    $quantityIndex = 0;
                    $bookingData = [];
                    foreach ($details['bookingData'] as $data) {
                        $timeId = $data['timeId'];
                        $commences = $data['commences'] ?? null;
                        $optionalData = $data['optionalData'] ?? [];
                        $pickupId = $data['pickupId'] ?? null;
                        $pickupLocation = $data['pickupLocation'] ?? null;
                        $redeemers = $data['redeemers'] ?? null;
                        $bookingData[] = [
                            "quantityIndex" => ++$quantityIndex,
                            "timeId" => $timeId,
                            "commences" => $commences,
                            "pickupId" => $pickupId,
                            "pickupLocation" => $pickupLocation,
                            "optionalData" => $optionalData,
                            "redeemers" => $redeemers
                        ];
                    }
                    $quantity = $quantityIndex;
                } elseif (empty($bookingData)) {
                    $quantity = $details['quantity'];
                    $timeId = $details['timeId'] ?? '0';
                    $commences = $details['commences'] ?? null;
                    $optionalData = $details['bookingData']['optionalData'] ?? [];
                    $bookingData = $details['bookingData'] ?? [];
                    for ($i = 1; $i <= $quantity; $i++) {
                        $bookingData[] = [
                            "quantityIndex" => $i,
                            "timeId" => $timeId,
                            "commences" => $commences,
                            "optionalData" => $optionalData
                        ];
                    }
                }

                $farePrices = $productDetails['faresprices'];

                // Find fareTypeId for the given productPricesDetailsId
                $fareTypeId = null;
                foreach ($farePrices as $fare) {
                    if ((string) $fare["productPricesDetailsId"] === (string) $productPriceDetailsId) {
                        $fareTypeId = $fare["fareTypeId"];
                        break;
                    }
                }

                if (
                    isset($fare['fareQtyRestrictions'])
                    && $quantity % (int) $fare['fareQtyRestrictions'] != 0
                ) {
                    return ServiceResponse::badRequest(
                        message: 'Quantity must be multiple of ' . $fare['fareQtyRestrictions']
                    );
                }

                if ($productDetails['apiProviderId'] > 0 && $productDetails['groupFaresForAvailabilityCheck'] == true) {
                    $productAvailabilitiesResponse = ProductService::getProductAvailabilitiesByProductAndRange(
                        agentToken: $defaultAgentAccessToken,
                        fareTypeId: $fareTypeId,
                        productId: $tdmsProductId,
                        startDate: $details['bookingDate'],
                        endDate: Carbon::parse($details['bookingDate'])->addDays(1)->toDateString()
                    );

                    if ($productAvailabilitiesResponse->isError()) {
                        return ServiceResponse::notFound(
                            message: 'Product availability not found',
                        );
                    }

                    $productAvailabilities = $productAvailabilitiesResponse->data;
                } else {
                    $productAvailabilities = ProductService::getProductAvailabilitiesFromApi(
                        $defaultAgentAccessToken,
                        $productPriceDetailsId,
                        $timeId,
                        $details['bookingDate'],
                        1,
                    );
                }
                if (
                    empty($productAvailabilities) ||
                    (isset($productAvailabilities['statusCode']) &&
                    $productAvailabilities['statusCode'] != 200)
                ) {
                    return ServiceResponse::notFound(
                        message: 'Product availability not found',
                    );
                }
                $result[] = new OrderItemRequestData(
                    product: $productDetails,
                    productPriceDetailsId: $productPriceDetailsId,
                    productLastUpdate: $productLastUpdate,
                    productBookingDetails: $bookingDetails,
                    productAvailabilities: $productAvailabilities[0],
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
        string $userId,
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
            ];
            if (!is_null($quote)) {
                $newCartData['quote_id'] = $quote->id;
            }
            array_push($cartItemsData, $newCartData);
        }
        return $cartItemsData;
    }

    public static function saveItems(
        string $userId,
        int $tdmsProductId,
        string $startDate,
        int $days,
        array $selectedAvailableIndices,
        ItemType $itemType,
        array $productPricesDetails,
        AddToQuote $addToQuote = null,
        bool $isDryRun = false,
    ) {
        try {
            $buildRequestDataResponse = self::buildOrderItemRequestData(
                userId: $userId,
                tdmsProductId: $tdmsProductId,
                productPricesDetails: $productPricesDetails,
                itemType: $itemType
            );
            if (!$buildRequestDataResponse->isSuccess()) {
                return $buildRequestDataResponse;
            }
            $orderItemsData = $buildRequestDataResponse->data;

            $now = Carbon::now();
            if ($isDryRun) {
                $cartItemsData = self::buildCartItemsData(
                    userId: $userId,
                    tdmsProductId: $tdmsProductId,
                    productVersion: null,
                    startDate: $startDate,
                    days: $days,
                    selectedAvailableIndices: $selectedAvailableIndices,
                    availabilityLastUpdatedAt: $now,
                    itemType: $itemType,
                    quote: null,
                    orderItemsData: $orderItemsData
                );
                $cachedProduct = Product::where('tdms_product_id', $tdmsProductId)
                                ->orderBy('version', 'desc')
                                ->first();

                $cartItemsData = array_map(
                    fn ($cartItemData) => array_merge($cartItemData, ['product' => $cachedProduct]),
                    $cartItemsData,
                );

                return ServiceResponse::success(data: $cartItemsData);
            }

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
                $addToQuote,
                $itemType,
            ) {
                $quote = null;
                if (!is_null($addToQuote)) {
                    if ($addToQuote->isNew) {
                        $quote = Quote::create([
                            'user_id' => $userId,
                            'title' => $addToQuote->title,
                        ]);
                    } else {
                        $quote = Quote::where('id', $addToQuote->quoteId)->first();
                    }
                }

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

                foreach ($cartItemsData as $cartItemData) {
                    $newCartItem = CartItem::create($cartItemData);
                    array_push($cartItems, $newCartItem);
                }

                return $cartItems;
            });

            return ServiceResponse::success(data: $cartItems);
        } catch (ServiceException $e) {
            Logger::error('Failed to save cart items', $e);
            return $e->toServiceResponse();
        }
    }

    public static function updateItemBookingData($data)
    {
        $cartItemIds = array_map(function ($item) {
            return $item['cartItemId'];
        }, $data);

        $cartItems = CartItem::whereIn('id', $cartItemIds)->get();

        DB::beginTransaction();
        foreach ($data as $item) {
            $cartItem = clone($cartItems)->where('id', $item['cartItemId'])->first();
            $product = Product::where('tdms_product_id', $cartItem->tdms_product_id)
                ->where('version', $cartItem->product_version)
                ->first();

            $farePrice = array_filter($product->json['faresprices'], function ($farePrice) use ($cartItem) {
                return $farePrice['productPricesDetailsId'] == $cartItem->product_price_details_id;
            });
            $numpax = array_values($farePrice)[0]['numPax'];

            $bookingDatas = [];
            foreach ($item['bookingData'] as $bookingData) {
                $redeemers = $bookingData['redeemers'] ?? [];
                if (count($redeemers) < $numpax && count($redeemers) > 0) {
                    // Number of redeemers less than numPax
                    // Fill with first redeemer
                    $bookingData['redeemers'] = array_merge(
                        $bookingData['redeemers'],
                        array_fill(0, $numpax - count($bookingData['redeemers']), $bookingData['redeemers'][0])
                    );
                } elseif (count($redeemers) > $numpax) {
                    // Number of redeemers more than numPax
                    // Remove from last
                    $bookingData['redeemers'] = array_slice($bookingData['redeemers'], 0, $numpax);
                }
                $bookingData['optionalData'] = $bookingData['optionalData'] ?? [];
                $bookingDatas[] = $bookingData;
            }
            $cartItem->update([
                "booking_quantity" => $item['quantity'],
                "booking_data" => $bookingDatas
            ]);
        };
        DB::commit();

        return ServiceResponse::success('Cart items updated successfully');
    }
}
