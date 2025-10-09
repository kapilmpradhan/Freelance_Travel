<?php

namespace App\Services;

use App\Logging\Logger;
use Illuminate\Support\Facades\Http;

class ProductService
{
    public static function getProductsLastUpdateFromApi($agentToken, $productIds)
    {
        $productIdsArrayToString = implode(',', $productIds);

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/lastupdate/{$productIdsArrayToString}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);

        if ($response == false) {
            return null;
        }
        return json_decode($response, true);
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
