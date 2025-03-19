<?php

namespace App\Services;

use App\Events\CompleteOrderEvent;
use App\Events\OrderComplete;
use Illuminate\Support\Facades\DB;
use App\Events\OrderPosted;
use App\Features\OrderDataValidationFeature;
use App\Jobs\CacheProductJob;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserOrder;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Carbon\Carbon;

class BookingService
{
    public static function getTotalChargeAmount($cartItems)
    {
        $totalAmount = 0;

        foreach ($cartItems as $cartItem) {
            if (isset($cartItem->availability['productPricingData']['RRP'])) {
                $totalAmount += $cartItem->availability['productPricingData']['RRP'] * $cartItem->booking_quantity;
            } else {
                $totalAmount += $cartItem->availability['FarePrice']['RRP'] * $cartItem->booking_quantity;
            }
        }

        return $totalAmount;
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
        $bookingData = $cartItem->booking_data;
        $bookingData['travelDate'] = $cartItem->booking_date;
        unset($bookingData['optionalData']);
        return $bookingData;
    }

    public static function buildRedeemersData($cartItems, $customers)
    {
        $redeemers = [];
        foreach ($customers as $customer) {
            $redeemer = [
                "title" => $customer['title'] ?? null,
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
                        ],
                        'optionalFields' => $optionalFields,
                    ];
                }
            }

            $redeemers[] = $redeemer;
        }
        return $redeemers;
    }

    public static function buildProductsData($cartItems)
    {
        $products = [];
        foreach ($cartItems as $cartItem) {
            $products[] = [
                "productPricesDetailsId" => strVal($cartItem->product_price_details_id),
                "qty" => $cartItem->booking_quantity,
            ];
        }

        return $products;
    }

    public static function buildOrderRequestData(
        string $userId,
        bool $processAsQuote,
        string $bookingReference,
        string $paymentMethodCode,
        $cartItems,
        $customers,
    ) {
        $totalChargeAmount = self::getTotalChargeAmount($cartItems);

        // build order data fields from the data present in FT system
        $orderData = [
            // maintain alphabetical order
            "agentRefrence" => "test",
            "emailVouchers" => 0,
            "totalCharged" => $totalChargeAmount,
            "processAsQuote" => $processAsQuote,
            "products" => self::buildProductsData($cartItems),
            "redeemers" => self::buildRedeemersData($cartItems, $customers),
        ];

        // prepare rest data for order from the external system

        // set rest of the prepared data
        // maintain alphabetical order
        $orderData['bookingReference'] = $bookingReference;
        $orderData['paymentMethod'] = $paymentMethodCode;

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
        array $customers,
        string $quoteId = null,
        bool $processAsQuote = true,
        bool $isDirectPurchase = false
    ) {
        $getAgentResponse = UserAgentService::getUserAgentIfExistsElseDefault($userId);
        if ($getAgentResponse->isError()) {
            return $getAgentResponse;
        }
        $agent = $getAgentResponse->data;

        if ($isDirectPurchase) {
            $cartItems = CartItem::userDirectPurchaseItems($userId);
        } else {
            $cartItems = (
                is_null($quoteId)
                ? CartItem::userCartItems($userId)
                : CartItem::userQuoteItems($userId, $quoteId)
            );
        }
        $cartItemIds = $cartItems->pluck('id')->toArray();
        if (empty($cartItemIds)) {
            return ServiceResponse::badRequest('No items available');
        }

        $onlinePaymentMethod = self::getOnlinePaymentMethod($agent->access_token);
        if (is_null($onlinePaymentMethod)) {
            throw new ServiceException('Missing online payment method');
        }

        $newBookingReference = TdmsService::getBookingRefrence($agent->access_token);
        $webAppReturnUrl = $onlinePaymentMethod['paymentReturnUrl'] . '/' . config(
            'vars.web_order_check_url',
            'order/check',
        ) . "?bookingReference={$newBookingReference}";
        Logger::debug("web app return url: {$webAppReturnUrl}");

        $orderRequestData = self::buildOrderRequestData(
            userId: $userId,
            processAsQuote: $processAsQuote,
            bookingReference: $newBookingReference,
            paymentMethodCode: $onlinePaymentMethod['code'],
            cartItems: $cartItems,
            customers: $customers,
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
                foreach ($validatedItemsData as $item) {
                    if ($item['status'] === 'Available') {
                        continue;
                    } else {
                        return ServiceResponse::badRequest(
                            message: 'Invalid order data',
                            data: $item
                        );
                    }
                }
            } catch (ServiceException $e) {
                return $e;
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
            quoteId: $quoteId,
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

    public static function postOrder(string $userId, string $intent, bool $processAsQuote = true)
    {
        $customers = CartCustomerDetail::where('user_id', $userId)->get()->toArray();
        $basePostOrderResponse = self::basePostOrder($userId, $intent, $customers, null, $processAsQuote);

        return $basePostOrderResponse;
    }

    public static function postOrderV2(
        string $userId,
        string $intent,
        string $quoteId = null,
        bool $processAsQuote = true,
        bool $isDirectPurchase = false
    ) {
        $user = User::where('uuid', $userId)->first();
        $checkIfUserContainsLeadCustomerDetailResponse = UserService::checkIfUserContainsLeadCustomerDetail($user);
        if ($checkIfUserContainsLeadCustomerDetailResponse->isError()) {
            return ServiceResponse::badRequest(message: 'Unable to get lead customer details');
        }
        $leadCustomer = [
            "email" => $user->email,
            "title" => $user->title ?? null,
            "first_name" => $user->first_name,
            "last_name" => $user->last_name,
            "phone_number" => $user->phone_number,
            "country_code" => $user->country_code,
            "date_of_birth" => $user->date_of_birth,
            "postal_code" => $user->post_code,
            "customer_index" => 0
        ];

        // Lead customer is indexed 0 so others customers index are incremented by 1 in memory.
        $customers = CartCustomerDetail::where('user_id', $userId)
                            ->where('is_direct_purchase', $isDirectPurchase)
                            ->when(!is_null($quoteId), fn ($query) => $query->where('quote_id', $quoteId))
                            ->orderBy('customer_index', 'desc')
                            ->get()
                            ->map(function ($customer) {
                                $customer->customer_index += 1;
                                return $customer;
                            })
                            ->toArray();
        $customers = array_merge([$leadCustomer], $customers);
        $basePostOrderResponse = self::basePostOrder(
            userId: $userId,
            intent: $intent,
            customers: $customers,
            quoteId: $quoteId,
            processAsQuote: $processAsQuote,
            isDirectPurchase: $isDirectPurchase
        );

        return $basePostOrderResponse;
    }

    public static function completeOrder(string $bookingReference)
    {
        $userOrder = UserOrder::where('booking_reference', $bookingReference)->first();
        if (is_null($userOrder)) {
            return ServiceResponse::notFound(message: 'Booking reference not found');
        }
        $cartItemIds = $userOrder->cart_item_ids;

        $cartItemQ = CartItem::whereIn('id', $cartItemIds);
        $quoteIds = (clone $cartItemQ)->select('quote_id')->distinct()->pluck('quote_id');

        DB::transaction(function () use ($userOrder, $cartItemQ, $quoteIds) {
            $userOrder->is_paid = true;
            $userOrder->save();

            $cartItemQ->update(['user_order_id' => $userOrder->id]);
            Quote::whereIn('id', $quoteIds)->update([
                'is_paid' => true,
                'user_order_id' => $userOrder->id
            ]);

            event(new CompleteOrderEvent(
                cartItems: $cartItemQ->get(),
                userOrder: $userOrder
            ));
        });

        return ServiceResponse::success();
    }
}
