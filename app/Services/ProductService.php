<?php

namespace App\Services;

use App\Logging\Logger;
use Illuminate\Support\Facades\Http;

class ProductService
{
    public static function getProductsLastUpdateFromApi($agentToken, array|int $productIds)
    {
        $productIdsFormatted = is_array($productIds) ?
            implode(',', $productIds) :
            $productIds;

        $requestUrl = config('vars.tdms_api_url') . "/product/lastupdate/{$productIdsFormatted}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);

        // Check if the response is successful
        if ($response->successful()) {
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve product last update',
                responseCode: $response->status(),
                data: $response->status(),
            );
        }
    }

    public static function getProductDetailsFromApi($agentToken, $product)
    {
        $productId = $product->tdms_product_id;

        return ProductService::getProductDetails(agentToken: $agentToken, productId: $productId);
    }

    public static function getProductDetails($agentToken, $productId)
    {

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/{$productId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $response = json_decode($response, true);

        if (!isset($response['results'])) {
            return null;
        }
        return $response;
    }

    public static function getProductDetailsV2($agentToken, $productId)
    {

        $requestUrl = config('vars.tdms_api_url') . "/product/{$productId}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);

        // Check if the response is successful
        if ($response->successful()) {
            return HttpResponse::success(
                data: $response->json()['results'][0],
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve product details',
                responseCode: $response->status(),
                data: $response->status(),
            );
        }
    }

    public static function getMultipleProductDetails($agentToken, array $productIds)
    {
        $productIdsToString = implode(",", $productIds);
        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/{$productIdsToString}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $response = json_decode($response, true);

        if (!isset($response['results'])) {
            return null;
        }
        return $response;
    }

    public static function getBookingDetails($agentToken, $productPricesDetailsId)
    {
        $requestUrl = config('vars.tdms_api_url') . "/bookingdetails/{$productPricesDetailsId}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);

        // Check if the response is successful
        if ($response->successful()) {
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve booking details',
                responseCode: $response->status(),
                data: $response->status(),
            );
        }
    }

    public static function getProductAvailabilitiesFromApi(
        $agentToken,
        $productPricesDetailsId,
        $timeId,
        $startDate,
        $days,
    ) {
        $requestUrl = config('vars.tdms_api_url')
                    . "/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/$days";
        $response = Http::withToken($agentToken)
            ->withHeaders([
                "Authorization: Bearer {$agentToken}",
                'Content-Type' => 'application/json',
            ])
            ->get($requestUrl);
        if ($response->successful()) {
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve availability',
                responseCode: $response->status(),
                data: $response->status(),
            );
        }
    }

    public static function getProductAvailabilitiesByProductAndRange(
        $agentToken,
        $productId,
        $fareTypeId,
        $startDate,
        $endDate,
    ) {
        $requestUrl = config('vars.tdms_api_url')
                    . "/checkAvailabilityByProductAndRange/{$productId}/{$fareTypeId}/{$startDate}/{$endDate}";

        $response = Http::withToken($agentToken)
            ->withHeaders([
                "Authorization: Bearer {$agentToken}",
                'Content-Type' => 'application/json',
            ])
            ->post($requestUrl);

        if ($response->successful()) {
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve availability',
                responseCode: $response->status(),
                data: $response->status(),
            );
        }
    }
}
