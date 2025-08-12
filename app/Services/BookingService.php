<?php

namespace App\Services;

use App\DTOs\ItemType;
use App\DTOs\ProductOrderData;
use App\DTOs\RedeemerBookingsOrderData;
use App\DTOs\RedeemerOrderData;
use App\DTOs\RedeemerProductsOrderData;
use App\Events\CompleteOrderEvent;
use App\Events\OrderPosted;
use App\Features\OrderDataValidationFeature;
use App\Jobs\CacheProductJob;
use App\Logging\Logger;
use App\Models\CartCustomerDetail;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserAgent;
use App\Models\UserOrder;
use App\Models\UserOrderCommission;
use Carbon\Carbon;
use Database\Factories\CartItemFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public static function getTotalChargeAmount(
        Collection $cartItems,
        ?float $pointsDollarsApplied
    ): float {
        $totalChargeAmount = $cartItems->sum(function ($cartItem) {
            $rrp = $cartItem->availability['productPricingData']['RRP']
                ?? $cartItem->availability['FarePrice']['RRP'];

            return $rrp * $cartItem->booking_quantity;
        });

        if ($pointsDollarsApplied) {
            $totalChargeAmount -= $pointsDollarsApplied;
        }

        return $totalChargeAmount;
    }

    public static function getTotalNumberOfItems($cartItems)
    {
        $totalQuantity = 0;
        foreach ($cartItems as $cartItem) {
            $totalQuantity += $cartItem->booking_quantity;
        }

        return $totalQuantity;
    }

    public static function getOnlinePaymentMethod($agentToken)
    {
        $availablePaymentMethods = TdmsService::getPaymentMethods($agentToken);

        foreach ($availablePaymentMethods as $paymentMethod) {
            if ($paymentMethod['supportsOnlinePayment']) {
                return $paymentMethod;
            }
        }
        return null;
    }

    public static function buildRedeemerProductBookingData($cartItem)
    {
        $datePriceCacheId = isset($cartItem->availability['datePriceCacheId'])
                            ? $cartItem->availability['datePriceCacheId']
                            : null;

        $bookingData = $cartItem->booking_data;
        $bookingData['travelDate'] = $cartItem->booking_date;
        $bookingData['datePriceCacheId'] = $datePriceCacheId;
        unset($bookingData['optionalData']);
        return $bookingData;
    }

    public static function buildRedeemersData($cartItems, $customers)
    {
        $redeemers = [];
        foreach ($customers as $customer) {
            $redeemer = [
                "title" => $customer['title'] ?? 'Mr',
                "emailAddress" => $customer['email'],
                "firstName" => $customer['first_name'],
                "lastName" => $customer['last_name'],
                "phone" => strVal($customer['phone_number']),
                "redeemerCountry" => $customer['country_code'],
                "dateOfBirth" => $customer['date_of_birth'],
                "postcode" => $customer['postal_code'],
                'products' => [],
            ];

            // Assumes all the products are booked for the first customer
            // TODO: populate product based on selection
            if ($customer['customer_index'] === 0) {
                foreach ($cartItems as $cartItem) {
                    $bookingData = $cartItem->booking_data;
                    $optionalData = $bookingData['optionalData'] ?? [];
                    $optionalFields = [];
                    foreach ($optionalData as $key => $value) {
                        $optionalFields[] = [
                            "optionalFieldId" => $key,
                            "fieldText" => $value
                        ];
                    }
                    $redeemer['products'][] = [
                        'productPricesDetailsId' => strVal($cartItem->product_price_details_id),
                        'redeemerQty' => $cartItem->booking_quantity,
                        'bookings' => [
                            self::buildRedeemerProductBookingData($cartItem),
                            'optionalFields' => $optionalFields,
                        ],
                    ];
                }
            }

            $redeemers[] = $redeemer;
        }
        return $redeemers;
    }

    public static function buildRedeemersDataV2($cartItems, $userId, $forDiscount = false)
    {
        $userRedeemers = CartCustomerDetail::where('user_id', $userId)->get();
        if ($forDiscount) {
            $fakeRedeemer = CartCustomerDetail::factory(count: 1)->make()->first();
        }

        $redeemers = [];
        $products = [];

        foreach ($cartItems as $cartItem) {
            $bookingDatas = $cartItem->booking_data;
            $datePriceCacheId = isset($cartItem->availability['datePriceCacheId'])
                            ? $cartItem->availability['datePriceCacheId']
                            : null;

            foreach ($bookingDatas as $bookingData) {
                if (!$forDiscount) {
                    $redeemerIds = $bookingData['redeemers'] ?? [];
                } else {
                    $redeemerIds = [$fakeRedeemer->id];
                }
                if (empty($redeemerIds)) {
                    throw new ServiceException('Redeemer not found', data: [
                        'cartItemId' => $cartItem->id,
                        'bookingData' => $bookingData
                    ], code: 400);
                }
                $isPrimaryRedeemer = true;
                foreach ($redeemerIds as $redeemerId) {
                    // Order data for redeemers
                    if (!$forDiscount) {
                        $userRedeemer = $userRedeemers->where('id', $redeemerId)->first();
                    } else {
                        $userRedeemer = $fakeRedeemer;
                    }

                    $isNewRedeemer = true;
                    $newRedeemer = new RedeemerOrderData(
                        redeemerId: $userRedeemer->id,
                        title: $userRedeemer->title,
                        email: $userRedeemer->email,
                        firstName: $userRedeemer->first_name,
                        lastName: $userRedeemer->last_name,
                        phoneNumber: $userRedeemer->phone_number,
                        countryCode: $userRedeemer->country_code,
                        dateOfBirth: $userRedeemer->date_of_birth,
                        postalCode: $userRedeemer->postal_code,
                    );
                    foreach ($redeemers as $redeemer) {
                        if ($redeemer->redeemerId == $userRedeemer->id) {
                            $isNewRedeemer = false;
                            break;
                        }
                    }

                    if ($isNewRedeemer) {
                        $redeemers[] = $newRedeemer;
                    }

                    // Order data for redeemer products
                    $isNewProduct = true;
                    $newProduct = new RedeemerProductsOrderData(
                        redeemerId: $redeemerId,
                        productPricesDetailsId: strVal($cartItem->product_price_details_id),
                        datePriceCacheId: $datePriceCacheId,
                        redeemerQuantity: 0
                    );

                    foreach ($products as $product) {
                        if (
                            $product->productPricesDetailsId == $newProduct->productPricesDetailsId
                            && $product->redeemerId == $newProduct->redeemerId
                            && $product->redeemerId == $newProduct->redeemerId
                            && $product->datePriceCacheId == $newProduct->datePriceCacheId
                        ) {
                            $isNewProduct = false;
                            break;
                        }
                    }

                    if ($isNewProduct) {
                        $products[] = $newProduct;
                        foreach ($redeemers as $redeemer) {
                            if ($redeemer->redeemerId == $newProduct->redeemerId) {
                                $redeemer->products[] = $newProduct;
                            }
                        }
                    }

                    // Order data for redeemer product bookings
                    $bookingComment = isset($bookingData['bookingComment'])
                                    ? $bookingData['bookingComment']
                                    : null;
                    $bookingDetailsComment = isset($bookingData['bookingDetailsComment'])
                                        ? $bookingData['bookingDetailsComment']
                                        : null;
                    $timeId = isset($bookingData['timeId'])
                                        ? $bookingData['timeId']
                                        : null;
                    $commences = isset($bookingData['commences'])
                                        ? $bookingData['commences']
                                        : null;
                    $pickupId = isset($bookingData['pickupId'])
                                        ? $bookingData['pickupId']
                                        : null;
                    $pickupLocation = isset($bookingData['pickupLocation'])
                                        ? $bookingData['pickupLocation']
                                        : null;
                    $dropoffId = isset($bookingData['dropoffId'])
                                        ? $bookingData['dropoffId']
                                        : null;
                    $dropoffLocation = isset($bookingData['dropoffLocation'])
                                        ? $bookingData['dropoffLocation']
                                        : null;
                    $optionalData = isset($bookingData['optionalData'])
                                        ? $bookingData['optionalData']
                                        : null;

                    $newBooking = new RedeemerBookingsOrderData(
                        redeemerId: $redeemerId,
                        productPricesDetailsId: $cartItem->product_price_details_id,
                        bookingComment: $bookingComment,
                        bookingDetailsComment: $bookingDetailsComment,
                        travelDate: $cartItem->booking_date,
                        timeId: $timeId,
                        commences: $commences,
                        pickupId: $pickupId,
                        pickupLocation: $pickupLocation,
                        dropoffId: $dropoffId,
                        dropoffLocation: $dropoffLocation,
                        datePriceCacheId: $datePriceCacheId,
                        optionalData: $optionalData
                    );

                    foreach ($products as $product) {
                        if (
                            $product->productPricesDetailsId == $newBooking->productPricesDetailsId
                            && $product->redeemerId == $newBooking->redeemerId
                            && $product->datePriceCacheId == $newBooking->datePriceCacheId
                        ) {
                            $product->redeemerQuantity = $isPrimaryRedeemer
                                                ? $product->redeemerQuantity + 1
                                                : $product->redeemerQuantity;
                            $product->bookings[] = $newBooking;
                        }
                    }
                    $isPrimaryRedeemer = false;
                }
            }
        }

        $redeemersData = [];
        foreach ($redeemers as $redeemer) {
            $redeemersData[] = $redeemer->toArray();
        }

        return $redeemersData;
    }

    public static function buildProductsData($cartItems)
    {
        $products = [];
        foreach ($cartItems as $cartItem) {
            $datePriceCacheId = isset($cartItem->availability['datePriceCacheId'])
                            ? $cartItem->availability['datePriceCacheId']
                            : null;

            $product = Product::where('tdms_product_id', $cartItem->tdms_product_id)
                            ->where('version', $cartItem->product_version)
                            ->first();

            $isNewProduct = true;
            $newProduct = new ProductOrderData(
                productPricesDetailsId: strVal($cartItem->product_price_details_id),
                quantity: $cartItem->booking_quantity,
                datePriceCacheId: $datePriceCacheId,
                cartItemIds: [$cartItem->id]
            );

            foreach ($products as $product) {
                if (
                    $product->productPricesDetailsId == $newProduct->productPricesDetailsId
                    && $product->datePriceCacheId == $newProduct->datePriceCacheId
                ) {
                    $product->cartItemIds[] = $cartItem->id;
                    $product->quantity += $newProduct->quantity;
                    $isNewProduct = false;
                    break;
                }
            }

            if ($isNewProduct) {
                $products[] = $newProduct;
            }
        }

        $productsData = [];
        foreach ($products as $product) {
            $productsData[] = $product->toArray();
        }
        return $productsData;
    }

    public static function buildOrderRequestData(
        UserAgent $userAgent,
        string $userId,
        bool $processAsQuote,
        string|null $bookingReference,
        string|null $paymentMethodCode,
        ?int $pointsApplied,
        Collection $cartItems,
        array $customers,
        bool $forDiscount = false
    ): array {
        $totalChargeAmount = self::getTotalChargeAmount(
            $cartItems,
            UserOrderCommissionService::pointsToDollars($pointsApplied, $userAgent->points_multiplier)
        );
        $orderProducts = self::buildProductsData($cartItems);
        if (empty($customers)) {
            $redeemers = self::buildRedeemersDataV2($cartItems, $userId, $forDiscount);
        } else {
            $redeemers = self::buildRedeemersData($cartItems, $customers);
        }
        // build order data fields from the data present in FT system
        $orderData = [
            // maintain alphabetical order
            "agentRefrence" => "test",
            "emailVouchers" => 0,
            "totalCharged" => $totalChargeAmount,
            "processAsQuote" => $processAsQuote,
            "products" => $orderProducts,
            "redeemers" => $redeemers,
        ];

        // prepare rest data for order from the external system

        // set rest of the prepared data
        // maintain alphabetical order
        $orderData['bookingReference'] = $bookingReference;
        $orderData['paymentMethod'] = $paymentMethodCode;

        if ($pointsApplied) {
            $orderData["cashbackApplied"] = $pointsApplied;
        }

        if (
            $userAgent->referral_source_id
            && $userAgent->created_at
                ->isAfter(now()->subMonths(config('vars.referral_source_id_period_months')))
        ) {
            $orderData['referralSourceId'] = $userAgent->referral_source_id;
        }

        Logger::debug('Order request data', $orderData);

        return $orderData;
    }

    public static function validateCartItemAvailability($agentToken, $cartItems)
    {
        $unavailableProducts = [];

        foreach ($cartItems as $cartItem) {
            $product = Product::where('tdms_product_id', $cartItem->tdms_product_id)->first();
            if (!$product) {
                CacheProductJob::dispatchSync(
                    user: null,
                    cartItem: $cartItem
                );
                $product = Product::where('tdms_product_id', $cartItem->tdms_product_id)->first();
            }

            $availability = ProductService::getProductAvailabilitiesFromApi(
                agentToken: $agentToken,
                productPricesDetailsId: $cartItem->product_price_details_id,
                timeId: 0,
                startDate: $cartItem->booking_date,
                days: 1
            )[0];

            $availableNumber = $availability['NumAvailable'];
            if ($availableNumber < $cartItem->booking_quantity) {
                $unavailableProducts[] = [
                    $cartItem->tdms_product_id => "Available quantity: {$availableNumber}"
                ];
            }
        };

        if (!empty($unavailableProducts)) {
            return ServiceResponse::badRequest(
                message: 'Some products or quantity not available',
                data: $unavailableProducts
            );
        }
        return ServiceResponse::success(
            message: "All items are available"
        );
    }

    public static function basePostOrder(
        string $userId,
        string $intent,
        ?int $pointsApplied,
        ItemType $itemType,
        bool $processAsQuote = true,
        array $customers = []
    ) {
        $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);
        if ($getAgentResponse->isError()) {
            return $getAgentResponse;
        }
        $agent = $getAgentResponse->data; /** @var UserAgent $agent */

        if ($itemType->forDiscount) {
            $cartItemData = $itemType->data;
            $cartItems = CartItemFactory::withProvidedData($cartItemData);
        } elseif ($itemType->isDirect) {
            $cartItems = CartItem::userDirectPurchaseItems($userId);
        } else {
            $cartItems = (
                !$itemType->isQuote
                ? CartItem::userCartItems($userId)
                : CartItem::userQuoteItems($userId, $itemType)
            );
        }
        $cartItemIds = $cartItems->pluck('id')->toArray();
        if (empty($cartItemIds)) {
            return ServiceResponse::badRequest('No items available');
        }

        if ($itemType->forDiscount) {
            $bookingReference = null;
            $onlinePaymentMethod = null;
        } else {
            $onlinePaymentMethod = self::getOnlinePaymentMethod($agent->access_token);
            if (is_null($onlinePaymentMethod)) {
                throw new ServiceException('Missing online payment method');
            }

            $bookingReference = TdmsService::getBookingRefrence($agent->access_token);
            $webAppReturnUrl = $onlinePaymentMethod['paymentReturnUrl'] . '/' . config(
                'vars.web_order_check_url',
                'order/check',
            ) . "?bookingReference={$bookingReference}";
            Logger::debug("web app return url: {$webAppReturnUrl}");
        }

        foreach ($cartItems as $cartItem) {
            $updateItemAvailabilityResponse = CartItemServiceV2::updateAvailabilityBeforeOrder(
                agentToken: $agent->access_token,
                cartItem: $cartItem
            );

            if ($updateItemAvailabilityResponse->isError()) {
                return $updateItemAvailabilityResponse;
            }
        }

        $orderRequestData = self::buildOrderRequestData(
            userAgent: $agent,
            userId: $userId,
            processAsQuote: $processAsQuote,
            bookingReference: $bookingReference,
            paymentMethodCode: $onlinePaymentMethod ? $onlinePaymentMethod['code'] : null,
            pointsApplied: $pointsApplied,
            cartItems: $cartItems,
            customers: $customers,
            forDiscount: $itemType->forDiscount
        );

        if (OrderDataValidationFeature::isEnabled()) {
            try {
                $validateOrderDataResponse = TdmsService::validateOrderData(
                    agentToken: $agent->access_token,
                    bookingReference: $orderRequestData['bookingReference'],
                    orderData: $orderRequestData
                );
                if ($validateOrderDataResponse->isError()) {
                    return $validateOrderDataResponse;
                }

                $validatedItemsData = $validateOrderDataResponse->data;


                $commission = $validatedItemsData['commission']['message']['estimatedCommission'] ?? 0;
                $totalRrp = $orderRequestData['totalCharged'];
                $commissionPercentage = round(((float) $commission / (float) $totalRrp) * 100, 2);
                $pointsAvailable = UserOrderCommissionService::pointsFromCommission(
                    (float) $commission,
                    $agent->points_multiplier
                );

                if ($itemType->forDiscount) {
                    return ServiceResponse::success(
                        data: [
                            'branch' => $agent->branch_code,
                            'commission' => $commissionPercentage,
                            'pointsAvailable' => $pointsAvailable,
                        ],
                        message: 'Order data validated successfully'
                    );
                }

                $discount = DiscountService::calcuateOverallDiscount(
                    commissionPercentage: $commissionPercentage,
                    user: User::find($userId)
                );
                $agentType = app('agentType');
                if ($agentType->isDefaultAgent) {
                    $orderRequestData['totalCharged'] -= $orderRequestData['totalCharged'] * $discount / 100;
                    $orderRequestData['totalCharged'] = round((float) $orderRequestData['totalCharged'], 2);
                }

                $overallStatus = [];
                foreach ($validatedItemsData as $item) {
                    if ($item['status'] === 'Available') {
                        continue;
                    }

                    if ($item['status'] === 'Commission Details') {
                        if (!isset($item['message']['errors'])) {
                            continue;
                        }
                    } else {
                        $productId = !empty($item['productDetail'])
                            ? $item['productDetail']['productId']
                            : null;

                        $productPricesDetailsId = !empty($item['productDetail'])
                            ? $item['productDetail']['productPricesDetailsId']
                            : null;


                        $overallStatus[] = [
                            'status' => $item['status'],
                            'productId' => $productId,
                            'productPricesDetailsId' => $productPricesDetailsId,
                            'errors' => $item['message'] ?? [],
                        ];
                    }
                }
                if (!empty($overallStatus)) {
                    return ServiceResponse::badRequest(
                        message: 'Invalid order data',
                        data: $overallStatus
                    );
                }
            } catch (ServiceException $e) {
                throw $e;
            }
        } else {
            $validateProductAvailabilityResponse = CartItemService::validateProductAvailability(
                agentToken: $agent->access_token,
                cartItems: $cartItems
            );
            if ($validateProductAvailabilityResponse->isError()) {
                return $validateProductAvailabilityResponse;
            }

            $get_validate_cart_items_response = BookingService::validateCartItemAvailability(
                $agent->access_token,
                $cartItems
            );

            if ($get_validate_cart_items_response->isError()) {
                return $get_validate_cart_items_response;
            };
        }

        $bookingReference = $orderRequestData['bookingReference'];
        $paymentAmount = $orderRequestData['totalCharged'];

        $placeOrderResponse = TdmsService::placeOrder(
            agentToken: $agent->access_token,
            data: $orderRequestData,
            userId: $userId,
        );

        $orderResponseData = $placeOrderResponse->data;
        if ($placeOrderResponse->isError()) {
            throw new ServiceException($orderResponseData['message']);
        }

        $getPaymentGatewayUriResponse = TdmsService::getPaymentGatewayUri(
            agentToken: $agent->access_token,
            bookingReference: $bookingReference,
            paymentAmount: $paymentAmount,
            returnUrl: $webAppReturnUrl,
        );
        if ($getPaymentGatewayUriResponse['success'] === false) {
            throw new ServiceException(
                message: 'Failed to get payment uri',
                data: [
                    'requestData' => [
                        'paymentAmount' => $paymentAmount,
                        'paymentMethod' => $onlinePaymentMethod['name'],
                    ],
                    'responseData' => $getPaymentGatewayUriResponse,
                ],
            );
        }

        $emailData = [];
        if ($intent === 'email-quote') {
            $agentData = [
                'emailAddress' => $agent->email,
            ];
            $user = User::where('uuid', $userId)->first();
            if (!empty($user->first_name)) {
                $agentData['firstName'] = $user->first_name;
                $agentData['lastName'] = $user->last_name;
            }
            $emailData = [
                'bookingReference' => $bookingReference,
                'quantity' => self::getTotalNumberOfItems(cartItems: $cartItems),
                'totalCharged' => $orderRequestData['totalCharged'],
                'redeemers' => $orderRequestData['redeemers'],
                'agent' => $agentData,
                'purchaseDate' => Carbon::now(),
                'paymentLink' => $getPaymentGatewayUriResponse['data']['quoteUrl']
            ];
        }

        event(new OrderPosted(
            userId: $userId,
            userAgentId: $agent->id,
            bookingReference: $bookingReference,
            cartItemIds: $cartItemIds,
            requestData: $orderRequestData,
            responseData: $orderResponseData,
            intent: $intent,
            emailData: $emailData,
            quoteId: $itemType->typeId,
            paymentGateway: [
                "redirectUrl" => $getPaymentGatewayUriResponse['data']['redirectUrl'],
                "quoteUrl" => $getPaymentGatewayUriResponse['data']['quoteUrl']
            ]
        ));

        if ($intent == 'pay-now') {
            return ServiceResponse::success(data: [
                "bookingReference" => $bookingReference,
                "payNow" => $getPaymentGatewayUriResponse['data']
            ]);
        }

        return ServiceResponse::success(data: ['bookingReference' => $bookingReference]);
    }

    public static function postOrder(
        string $userId,
        string $intent,
        ?int $pointsApplied,
        ItemType $itemType,
        bool $processAsQuote = true
    ): ?ServiceResponse {
        return self::basePostOrder(
            userId: $userId,
            intent: $intent,
            pointsApplied: $pointsApplied,
            itemType: $itemType,
            processAsQuote: $processAsQuote
        );
    }

    public static function completeOrder(string $bookingReference)
    {
        $userOrder = UserOrder::where('booking_reference', $bookingReference)->first();
        if (is_null($userOrder)) {
            return ServiceResponse::notFound(message: 'Booking reference not found');
        }
        $cartItemIds = $userOrder->cart_item_ids;

        $cartItemQ = CartItem::whereIn('id', $cartItemIds);
        $quoteIds = (clone $cartItemQ)->select('quote_id')->distinct()->pluck('quote_id')->filter()->toArray();
        $isDirectPurchase = (clone $cartItemQ)->where('is_direct_purchase', true)->exists();

        DB::transaction(function () use ($userOrder, $cartItemQ, $quoteIds, $isDirectPurchase) {
            $userOrder->is_paid = true;
            $userOrder->save();

            $cartItemQ->update(['user_order_id' => $userOrder->id]);
            Quote::whereIn('id', $quoteIds)->update([
                'is_paid' => true,
                'user_order_id' => $userOrder->id
            ]);
            CartCustomerDetail::where('user_id', $userOrder->user_id)
                ->where('user_order_id', null)
                ->where('is_primary', false)
                ->where('is_direct_purchase', $isDirectPurchase)
                ->when(!empty($quoteIds), fn ($query) => $query->whereIn('quote_id', $quoteIds))
                ->update(['user_order_id' => $userOrder->id]);
            UserOrderCommission::where('user_id', $userOrder->user_id)
                ->where('user_order_id', null)
                ->when(!empty($quoteIds), fn ($query) => $query->whereIn('quote_id', $quoteIds))
                ->where('is_direct_purchase', $isDirectPurchase)
                ->when(empty($quoteIds) && !$isDirectPurchase, fn ($query) => $query->where('is_cart', true))
                ->update(['user_order_id' => $userOrder->id]);

            event(new CompleteOrderEvent(
                cartItems: $cartItemQ->get(),
                userOrder: $userOrder
            ));
        });

        return ServiceResponse::success();
    }
}
