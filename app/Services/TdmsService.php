<?php

namespace App\Services;

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

        $response = Http::withHeaders([
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

    public static function getCustomerBookings($customerEmail, $page, $afterDate)
    {
        $url = config('vars.tdms_customer_api_url') . "?email=" . rawurlencode($customerEmail);
        if (!empty($page)) {
            $url .= "&page=" . rawurlencode($page);
        }

        if (!empty($afterDate)) {
            $url .= "&afterDate=" . rawurlencode($afterDate);
        }

        // Credentials from config
        $username = config('vars.tdms_customer_api_username');
        $password = config('vars.tdms_customer_api_password');

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get($url);

        // Check if the request was successful
        $data = $response->json();
        if ($response->successful()) {
            return $data;
        }
        Logger::error(
            message: 'Error fetching customer orders',
            extra: [
                "customerEmail" => $$customerEmail,
                "page" => $page,
                "afterData" => $afterDate,
                "responseData" => $data
            ]
        );
        return null;
    }

    public static function convertQuoteToOrder($agentToken, $bookingReference)
    {
        $url = config('vars.tdms_api_url') . "/order/{$bookingReference}/convertQuote";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$agentToken}"
        ])
        ->post($url);

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

        $responseStatus = $response->status();

        if ($responseStatus == 200) {
            return ServiceResponse::success();
        } elseif ($responseStatus == 404) {
            return ServiceResponse::notFound();
        } else {
            Logger::error(
                message: "Error while fetching customer order detail",
                extra: ["orderId" => $orderId]
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
}
