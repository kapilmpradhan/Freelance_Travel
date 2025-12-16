<?php

namespace App\Services;

use App\Logging\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ProductService
{
    public static function getProductsLastUpdate($agentToken, array|int $productIds, $getCached = true)
    {
        $cachedProductsLastUpdate = [];
        $notCachedProductsLastUpdate = [];
        if ($getCached) {
            foreach (is_array($productIds) ? $productIds : [$productIds] as $productId) {
                $cachedProductsLastUpdateResponse = UserCacheService::getCachedProductLastUpdate($productId);
                if ($cachedProductsLastUpdateResponse->isSuccess()) {
                    $cachedProductsLastUpdate += $cachedProductsLastUpdateResponse->data;
                } else {
                    $notCachedProductsLastUpdate[] = $productId;
                }
            }
            if (empty($notCachedProductsLastUpdate)) {
                return ServiceResponse::success($cachedProductsLastUpdate);
            } else {
                $productIds = $notCachedProductsLastUpdate;
            }
        }

        $productIdsFormatted = is_array($productIds) ?
            implode(',', $productIds) :
            $productIds;

        $requestUrl = config('vars.tdms_api_url') . "/product/lastupdate/{$productIdsFormatted}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);
        $getData = $response->json();
        // Check if the response is successful
        if ($response->successful()) {
            if ($getCached && !empty($notCachedProductsLastUpdate)) {
                $cachedProductsLastUpdate += $response->json();
                return ServiceResponse::success($cachedProductsLastUpdate);
            }

            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            $responseData = $response->json();
            if (
                !$responseData ||
                str_contains($responseData['message'], 'do not exist')
            ) {
                return HttpResponse::failed(
                    message: 'One or more products do not exist',
                    responseCode: 404,
                    data: $response->json(),
                );
            }
            return HttpResponse::failed(
                message: 'Failed to retrieve product last update',
                responseCode: $response->status(),
                data: $response->json(),
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

    public static function getProductDetailsV2($agentToken, int|array $productIds, $getCached = true)
    {
        $cachedProductsData = [];
        $notCachedProducts = [];
        if ($getCached) {
            foreach (is_array($productIds) ? $productIds : [$productIds] as $productId) {
                $cachedProductsResponse = UserCacheService::getCachedProductDetails($productId);
                if ($cachedProductsResponse->isSuccess()) {
                    $cachedProductsData += $cachedProductsResponse->data;
                } else {
                    $notCachedProducts[] = $productId;
                }
            }
            if (empty($notCachedProducts)) {
                return ServiceResponse::success($cachedProductsData);
            } else {
                $productIds = $notCachedProducts;
            }
        }
        $productIdsFormatted = is_array($productIds) ?
            implode(',', $productIds) :
            $productIds;

        $requestUrl = config('vars.tdms_api_url') . "/product/{$productIdsFormatted}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);

        // Check if the response is successful
        $data = $response->json()['results'];
        if ($response->successful() && count($data) > 0) {
            if ($getCached && !empty($notCachedProductsLastUpdate)) {
                $cachedProductsData += $response->json();
                return ServiceResponse::success($cachedProductsData);
            }
            return HttpResponse::success(
                data: is_array($productIds) ? $data : $data[0],
                responseCode: $response->status(),
            );
        } else {
            return HttpResponse::failed(
                message: 'Failed to retrieve product details',
                responseCode: 400,
                data: $data,
            );
        }
    }

    public static function getBookingDetails($agentToken, $productPricesDetailsId, $getCached = true)
    {
        if ($getCached) {
            $cachedBookingDetailsResponse = UserCacheService::getCachedProductPriceBookingDetails(
                ppdid: $productPricesDetailsId
            );
            if ($cachedBookingDetailsResponse->isSuccess()) {
                return ServiceResponse::success($cachedBookingDetailsResponse->data);
            }
        }
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
                data: $response->json()
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
            Logger::debug(
                message: 'Failed to retrieve availability',
                data: $response->json(),
            );
            return HttpResponse::failed(
                message: 'Failed to retrieve availability',
                responseCode: $response->status(),
                data: $response->json(),
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
            Logger::debug(
                message: 'Failed to retrieve availability',
                data: $response->json(),
            );
            return HttpResponse::failed(
                message: 'Failed to retrieve availability',
                responseCode: $response->status(),
                data: $response->json(),
            );
        }
    }

    public static function getFarepriceAvailability(
        $agent,
        $productId,
        $productPricesDetailsId,
        $apiProviderId,
        $groupFaresForAvailabilityCheck,
        $fareTypeId,
        $bookingDate,
        $timeId,
        $getCached = true
    ) {
        if ($getCached) {
            $cachedAvailabilityResponse = UserCacheService::getCachedProductPriceAvailability(
                ppdid: $productPricesDetailsId,
                bookingDate: $bookingDate,
                timeId: $timeId
            );
            if ($cachedAvailabilityResponse->isSuccess()) {
                return ServiceResponse::success($cachedAvailabilityResponse->data);
            }
        }
        if ($apiProviderId > 0 && $groupFaresForAvailabilityCheck) {
            $productAvailabilitiesResponse = ProductService::getProductAvailabilitiesByProductAndRange(
                agentToken: $agent->access_token,
                fareTypeId: $fareTypeId,
                productId: $productId,
                startDate: $bookingDate,
                endDate: Carbon::parse($bookingDate)->addDays(1)->toDateString()
            );

            if ($productAvailabilitiesResponse->isError()) {
                return ServiceResponse::notFound(
                    message: 'Product availability not found',
                );
            }

            $productAvailabilities = $productAvailabilitiesResponse->data;
        } else {
            $productAvailabilitiesResponse = ProductService::getProductAvailabilitiesFromApi(
                $agent->access_token,
                $productPricesDetailsId,
                $timeId,
                $bookingDate,
                1,
            );

            if ($productAvailabilitiesResponse->isError()) {
                return ServiceResponse::notFound(
                    message: 'Product availability not found',
                );
            }

            $productAvailabilities = $productAvailabilitiesResponse->data;
        }

        if (empty($productAvailabilities)) {
            return ServiceResponse::notFound(
                message: 'Product availability not found',
            );
        }

        return ServiceResponse::success(data: $productAvailabilities[0]);
    }
}
