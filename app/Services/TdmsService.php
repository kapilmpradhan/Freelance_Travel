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
        $url = config('vars.tdms_api_url') . "/customerOrders?email={$customerEmail}";

        // Credentials from config
        $username = config('vars.default_token_agent_username');
        $password = config('vars.default_token_agent_password');

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

        if ($response->successful()) {
            $data = $response->json();
            return $data;
        }
        Logger::error("Failed to create order", extra: [
            'userId' => $userId,
            'requestData' => $data,
            'response' => $response,
        ]);
        return null;
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
}
