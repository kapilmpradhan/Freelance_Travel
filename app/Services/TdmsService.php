<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use App\Logging\Logger;

class TdmsService
{
    public static function getAgentToken(string $username, string $password, string $userId = null)
    {
        $url = config('vars.tdms_api_url') . '/agentToken';

        $data = [
            'username' => $username,
            'password' => $password
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->withBody(json_encode($data))
        ->post($url);

        $statusCode = $response->status();
        if ($statusCode !== 200) {
            Logger::error("Failed to get agent token", extra: [
                'userId' => $userId,
                'username' => $username,
                'password' => $password,
                'responseStatusCode' => $statusCode,
            ]);
            return null;
        }

        $data = json_decode($response, true);
        return $data;
    }

    public static function getCustomerLastOrderBranch($customerEmail)
    {
        $url = config('vars.tdms_customer_api_url') . "?email={$customerEmail}";

        // Credentials from config
        $username = config('vars.tdms_customer_api_username');
        $password = config('vars.tdms_customer_api_password');

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if ($data['orders']) {
                return substr(end($data['orders'])['bookingReference'], 0, 3);
            }
        }
        return null;
    }

    public static function getBookingRefrence($agentToken)
    {
        $url = config('vars.tdms_api_url') . "/bookingreference";

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->post($url);

        $data = $response->json();
        if ($response->successful()) {
            return $data['bookingReference'];
        }
        return null;
    }

    public static function getPaymentMethods($agentToken)
    {
        $url = config('vars.tdms_api_url') . "/agent/paymentmethods";

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if (!$data['paymentMethods']) {
                return null;
            }
            return $data['paymentMethods'];
        }
        return null;
    }

    public static function placeOrder($agentToken, $data, $userId)
    {
        $url = config('vars.tdms_api_url') . "/order";

        $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->withBody(json_encode($data))
            ->post($url);

        $data = $response->json();
        if ($response->successful()) {
            return ServiceResponse::success(
                message: "Order placed",
                data: $data
            );
        }
        Logger::error("Failed to create order", extra: [
            'userId' => $userId,
            'requestData' => $data,
            'responseData' => [
                "statusCode" => $response->status(),
                "headers" => $response->headers(),
                "body" => $response->body(),
            ]
        ]);
        return ServiceResponse::badRequest(
            message: "Failed to create order",
            data: $data
        );
    }

    public static function getPaymentGatewayUri($agentToken, $bookingReference, $paymentAmount, $returnUrl)
    {
        $url = config('vars.tdms_api_url') . "/pgw/authenticate/{$bookingReference}";

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->withQueryParameters([
                "paymentAmount" => $paymentAmount,
                "returnUrl" => 'https://' . $returnUrl
            ])
            ->get($url);

        // Check if the request was successful
        $data = $response->json();
        if ($response->successful()) {
            return [
                "success" => true,
                "data" => $data
            ];
        }
        return [
            "success" => false,
            "error" => $data['statusText'],
            "message" => $data["message"]
        ];
    }

    public static function getCustomerBookings($customerEmail)
    {
        $url = config('vars.tdms_customer_api_url') . "?email=" . rawurlencode($customerEmail);

        // Credentials from config
        $username = config('vars.tdms_customer_api_username');
        $password = config('vars.tdms_customer_api_password');

        $allOrders = [];
        $page = 1;
        while (true) {
            $url .= "&page=" . rawurlencode($page);
            $response = Http::withBasicAuth($username, $password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->get($url);

            // Check if the request was successful
            $data = $response->json();
            if ($response->successful()) {
                $allOrders = array_merge($allOrders, $data['orders']);
                if ($data['has_more'] == false) {
                    break;
                } else {
                    $page++;
                    continue;
                }
            }
            Logger::error(
                message: 'Error fetching customer orders',
                extra: [
                    "customerEmail" => $customerEmail,
                    "responseData" => $data,
                    "page" => $page
                ]
            );
        }

        return ServiceResponse::success(
            data: ['orders' => $allOrders]
        );
    }

    public static function convertQuoteToOrder($agentToken, $bookingReference)
    {
        $url = config('vars.tdms_api_url') . "/order/{$bookingReference}/convertQuote";

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->post($url);
        } catch (Exception $e) {
            Logger::error(
                message: 'Server error while converting quote to order',
                extra: ['bookingReference' => $bookingReference, 'exception' => $e]
            );
            throw new ServiceException('Server error while converting quote to order');
        }

        // Check if the request was successful
        $data = $response->json();
        if (isset($data['errors'])) {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }

        if (!$response->successful()) {
            Logger::error(
                message: 'Error converting quote to order',
                extra: [
                    "bookingReference" => $bookingReference,
                    "responseData" => $data
                ]
            );
        }

        return ServiceResponse::success();
    }

    public static function checkIfCustomerOrderStatusIsOrder($agentToken, $orderId)
    {
        try {
            $url = config('vars.tdms_api_url') . "/customerOrderDetail";

            $response = Http::withQueryParameters([
                "searchOnlyStatus" => "Order",
                "orderId" => $orderId
            ])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$agentToken}"
            ])
            ->get($url);
        } catch (Exception $e) {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["orderId" => $orderId, "exception" => $e]
            );
            throw new ServiceException(message: 'Server error while fetching customer order detail');
        }

        $responseStatus = $response->status();

        if ($responseStatus == 200) {
            return ServiceResponse::success();
        } elseif ($responseStatus == 404) {
            return ServiceResponse::notFound();
        } else {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["orderId" => $orderId, "response" => $response->json()]
            );
            return ServiceResponse::badRequest(message: 'Internal server error');
        }
    }

    public static function validateOrderData($agentToken, $bookingReference, $orderData)
    {
        $url = config('vars.tdms_api_url') . "/validateCart/{$bookingReference}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$agentToken}"
        ])
        ->withBody(json_encode($orderData))
        ->post($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "bookingReference" => $bookingReference,
                        "orderData" => $orderData,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getCategoriesByType($type, $agentToken, $countryId = 20)
    {
        $url = config('vars.tdms_api_url') . "/categories/{$type}?countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getProductsByCategory($type, $typeId, $agentToken, $countryId = 20)
    {
        $url = config('vars.tdms_api_url') . "/products?{$type}={$typeId}&countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getProductsByMultipleCategories(array $categoriesIdByTypes, $agentToken, $countryId = 20)
    {
        $query = '';
        foreach ($categoriesIdByTypes as $type => $categoryIds) {
            $ids = implode(',', $categoryIds);
            $query = "$query$type=$ids";
        }
        $url = config('vars.tdms_api_url') . "/products?countries={$countryId}&$query";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            if (!isset($data['message'])) {
                Logger::error(
                    message: 'Error validating order data',
                    extra: [
                        "type" => $type,
                        "responseData" => $data
                    ]
                );
                throw new ServiceException(
                    message: 'Internal server error',
                    data: $data,
                    code: $response->status()
                );
            } else {
                return ServiceResponse::badRequest(
                    message: $data['message']
                );
            }
        }
    }

    public static function getCountries($agentToken)
    {
        $url = config('vars.tdms_api_url') . "/categories?countries";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }

    public static function getCountryRegions($agentToken, $countryId)
    {
        $url = config('vars.tdms_api_url') . "/categories/regions?countries={$countryId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }

    public static function getProductsByRegion($agentToken, $countryId, $regionId)
    {
        $url = config('vars.tdms_api_url') . "/products?countries={$countryId}&regions={$regionId}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            "Authorization" => "Bearer {$agentToken}"
        ])->get($url);

        $data = $response->json();

        if ($response->status() == 200) {
            return ServiceResponse::success(
                data: $data['results']
            );
        } else {
            return ServiceResponse::badRequest(
                message: $data['message']
            );
        }
    }
}
