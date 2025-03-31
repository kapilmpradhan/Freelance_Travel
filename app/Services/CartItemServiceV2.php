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
        foreach ($productPricesDetails as $productPriceDetails) {
            $productPriceDetailsId = $productPriceDetails['productPricesDetailsId'];
            $quantityDetails = $productPriceDetails['quantityDetails'];

            $bookingDetailsResponse = ProductService::getBookingDetails(
                agentToken: $defaultAgentAccessToken,
                productPricesDetailsId: $productPriceDetails['productPricesDetailsId'],
            );

            if ($bookingDetailsResponse->isError()) {
                if ($bookingDetailsResponse->statusCode != 404) {
                    return ServiceResponse::notFound('Booking details not found');
                }

                $errorMessage = 'Failed to load booking details';
                Logger::error("{$errorMessage}: {$bookingDetailsResponse->message}");
                throw new ServiceException(message: $errorMessage);
            }
            $bookingDetails = $bookingDetailsResponse->data;

            foreach ($quantityDetails as $details) {
                $quantity = $details['quantity'];
                $timeId = $details['timeId'] ?? '0';
                $commences = $details['commences'] ?? null;
                $bookingData = $details['bookingData'] ?? [];

                if (empty($bookingData)) {
                    for ($i = 1; $i <= $quantity; $i++) {
                        $bookingData[] = [
                            "quantityIndex" => $i,
                            "timeId" => $timeId,
                            "commences" => $commences,
                        ];
                    }
                }

                if ($productDetails['apiProviderId'] > 0 && $productDetails['groupFaresForAvailabilityCheck'] == true) {
                    $farePrices = $productDetails['faresprices'];

                    // Find fareTypeId for the given productPricesDetailsId
                    $fareTypeId = null;
                    foreach ($farePrices as $fare) {
                        if ($fare["productPricesDetailsId"] === $productPriceDetailsId) {
                            $fareTypeId = $fare["fareTypeId"];
                            break;
                        }
                    }
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
                if (empty($productAvailabilities)) {
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
                    commences: $commences,
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
            } else {
                $selectedItemIndex++;
            }

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
                productPricesDetails: $productPricesDetails
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
            $cartItem->update([
                "quantity" => $item['quantity'],
                "booking_data" => $item['bookingData']
            ]);
        };
        DB::commit();

        return ServiceResponse::success('Cart items updated successfully');
    }
}
