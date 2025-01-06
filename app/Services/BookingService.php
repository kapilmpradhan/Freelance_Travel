<?php

namespace App\Services;

use App\Events\OrderPosted;
use App\Jobs\SendShareMailJob;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\CartCustomerDetail;
use App\Models\User;
use App\Services\TdmsService;
use App\Services\UserAgentService;

class BookingService
{
    public static function getTotalChargeAmount($cartItems)
    {
        $totalAmount = 0;

        foreach ($cartItems as $cartItem) {
            $totalAmount += $cartItem->availability['productPricingData']['RRP'] * $cartItem->booking_quantity;
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
        $booking_details = $cartItem->booking_details;
        return [
            "timeId" => $cartItem->availability['BookingTimeID'],
            "bookingComment" => "",
            "travelDate" => $cartItem->availability['BookingDate'],
            "pickupLocation" => $booking_details['pickupDetail']['pickupLocation'] ?? null,
            "pickupTime" => $booking_details['pickupDetail']['pickupTime'] ?? null,
            "pickupId" => $booking_details['pickupDetail']['pickupId'] ?? null,
        ];
    }

    public static function buildRedeemersData($cartItems, $customers)
    {
        $redeemers = [];
        foreach ($customers as $customer) {
            $redeemer = [
                "emailAddress" => $customer->email,
                "firstName" => $customer->first_name,
                "lastName" => $customer->last_name,
                "phone" => strVal($customer->phone_number),
                "redeemerCountry" => "036",
                "dateOfBirth" => $customer->date_of_birth,
                "postcode" => $customer->postal_code,
                'products' => [],
            ];

            // Assumes all the products are booked for the first customer
            // TODO: populate product based on selection
            if ($customer->customer_index === 0) {
                foreach ($cartItems as $cartItem) {
                    $bookingData = $cartItem->booking_data ?? [];
                    $optionalFields = [];
                    foreach ($bookingData as $key => $value) {
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

    public static function postOrder(string $userId, string $intent, bool $processAsQuote = true)
    {
        $agent = UserAgentService::getUserProfileAgent($userId);

        $cartItems = CartItem::userCartItems($userId)->get();
        $cartItemIds = $cartItems->pluck('id')->toArray();
        $customers = CartCustomerDetail::where('user_id', $userId)->get();

        $onlinePaymentMethod = self::getOnlinePaymentMethod($agent->access_token);
        if (is_null($onlinePaymentMethod)) {
            throw new ServiceException('Missing online payment method');
        }

        $newBookingReference = TdmsService::getBookingRefrence($agent->access_token);

        $orderRequestData = self::buildOrderRequestData(
            userId: $userId,
            processAsQuote: $processAsQuote,
            bookingReference: $newBookingReference,
            paymentMethodCode: $onlinePaymentMethod['code'],
            cartItems: $cartItems,
            customers: $customers,
        );

        $bookingReference = $orderRequestData['bookingReference'];
        $paymentAmount = $orderRequestData['totalCharged'];

        $orderResponse = TdmsService::placeOrder(
            agentToken: $agent->access_token,
            data: $orderRequestData,
            userId: $userId,
        );
        if (is_null($orderResponse)) {
            throw new ServiceException(message: "Failed to submit order");
        }

        $getPaymentGatewayUriResponse = TdmsService::getPaymentGatewayUri(
            agentToken: $agent->access_token,
            bookingReference: $bookingReference,
            paymentAmount: $paymentAmount,
            returnUrl: $onlinePaymentMethod['paymentReturnUrl'],
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
                'redeemers' => $orderRequestData['redeemers'],
                'agent' => $agentData,
                'purchaseDate' => null,
                'paymentLink' => $getPaymentGatewayUriResponse['data']['redirectUrl']
            ];
        }

        event(new OrderPosted(
            userId: $userId,
            bookingReference: $bookingReference,
            cartItemIds: $cartItemIds,
            requestData: $orderRequestData,
            responseData: $orderResponse,
            intent: $intent,
            emailData: $emailData,
        ));

        if ($intent == 'pay-now') {
            return ServiceResponse::success(data: $getPaymentGatewayUriResponse['data']);
        }

        return ServiceResponse::success(data: $orderRequestData);
    }
}
