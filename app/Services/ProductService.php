<?php

namespace App\Services;

use App\Logging\Logger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class ProductService
{
    public static function getProductsLastUpdate($agentToken, array|int $productIds, $getCached = true)
    {
        $productIdsArray = is_array($productIds) ? $productIds : [$productIds];

        Logger::debug('Getting products last update', [
            'log_file' => config('logging.log_files.products'),
            'product_ids' => implode(',', $productIdsArray),
            'get_cached' => $getCached,
            'action' => 'get_products_last_update_start',
        ]);

        $cachedProductsLastUpdate = [];
        $notCachedProductsLastUpdate = [];
        if ($getCached) {
            foreach ($productIdsArray as $productId) {
                $cachedProductsLastUpdateResponse = UserCacheService::getCachedProductLastUpdate($productId);
                if ($cachedProductsLastUpdateResponse->isSuccess()) {
                    $cachedProductsLastUpdate += $cachedProductsLastUpdateResponse->data;
                } else {
                    $notCachedProductsLastUpdate[] = $productId;
                }
            }
            if (empty($notCachedProductsLastUpdate)) {
                Logger::debug('Products last update retrieved from cache', [
                    'log_file' => config('logging.log_files.products'),
                    'product_ids' => implode(',', $productIdsArray),
                    'action' => 'products_last_update_cache_hit',
                ]);
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
            Logger::debug('Products last update retrieved from API', [
                'log_file' => config('logging.log_files.products'),
                'product_ids' => $productIdsFormatted,
                'action' => 'products_last_update_api_success',
            ]);

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
                Logger::debug('Products do not exist', [
                    'log_file' => config('logging.log_files.products'),
                    'product_ids' => $productIdsFormatted,
                    'action' => 'products_not_found',
                ]);
                return HttpResponse::failed(
                    message: 'One or more products do not exist',
                    responseCode: 404,
                    data: $response->json(),
                );
            }
            Logger::error('Failed to retrieve product last update', data: [
                'log_file' => config('logging.log_files.products'),
                'product_ids' => $productIdsFormatted,
                'response' => $responseData,
                'action' => 'products_last_update_api_failed',
            ]);
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
        Logger::debug('Getting product details', [
            'log_file' => config('logging.log_files.products'),
            'product_id' => $productId,
            'action' => 'get_product_details_start',
        ]);

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
            Logger::debug('Product details not found', [
                'log_file' => config('logging.log_files.products'),
                'product_id' => $productId,
                'action' => 'product_details_not_found',
            ]);
            return null;
        }

        Logger::debug('Product details retrieved', [
            'log_file' => config('logging.log_files.products'),
            'product_id' => $productId,
            'action' => 'product_details_retrieved',
        ]);
        return $response;
    }

    public static function getProductDetailsV2($agentToken, int|array $productIds, $getCached = true)
    {
        $productIdsToArray = is_array($productIds) ? $productIds : [$productIds];

        if ($getCached) {
            $cachedProductsData = [];
            $notCachedProducts = [];
            foreach ($productIdsToArray as $productId) {
                $cachedProductsResponse = UserCacheService::getCachedProductDetails($productId);
                if ($cachedProductsResponse->isSuccess()) {
                    $cachedProductsData += $cachedProductsResponse->data;
                } else {
                    $notCachedProducts[] = $productId;
                }
            }
            if (empty($notCachedProducts)) {
                return ServiceResponse::success($cachedProductsData);
            }
            $productIdsFormatted = implode(',', $notCachedProducts);
        } else {
            $productIdsFormatted = implode(',', $productIdsToArray);
        }

        $requestUrl = config('vars.tdms_api_url') . "/product/{$productIdsFormatted}";

        // Send the HTTP GET request
        $response = Http::withToken($agentToken)
            ->acceptJson()
            ->get($requestUrl);

        if ($response->failed()) {
            Logger::error('Failed to retrieve product details V2', data: [
                'log_file' => config('logging.log_files.products'),
                'product_ids' => $productIdsFormatted,
                'action' => 'product_details_v2_api_failed',
            ]);
            return HttpResponse::failed(
                message: 'Failed to retrieve product details',
                responseCode: $response->status(),
                data: $response->json(),
            );
        }

        // Check if the response is successful
        $data = $response->json()['results'];
        if (count($data) > 0) {
            Logger::debug('Product details V2 retrieved from API', [
                'log_file' => config('logging.log_files.products'),
                'product_ids' => $productIdsFormatted,
                'action' => 'product_details_v2_api_success',
            ]);

            if ($getCached) {
                $cachedProductsData += $data;
                return ServiceResponse::success($cachedProductsData);
            }
            return HttpResponse::success(
                data: is_array($productIds) ? $data : $data[0],
                responseCode: $response->status(),
            );
        } else {
            Logger::error('Failed to retrieve product details V2', data: [
                'log_file' => config('logging.log_files.products'),
                'product_ids' => $productIdsFormatted,
                'action' => 'product_details_v2_api_failed',
            ]);
            return HttpResponse::failed(
                message: 'Failed to retrieve product details',
                responseCode: 400,
                data: $data,
            );
        }
    }

    public static function getBookingDetails($agentToken, $productPricesDetailsId, $getCached = true)
    {
        Logger::debug('Getting booking details', [
            'log_file' => config('logging.log_files.products'),
            'ppdid' => $productPricesDetailsId,
            'get_cached' => $getCached,
            'action' => 'get_booking_details_start',
        ]);

        if ($getCached) {
            $cachedBookingDetailsResponse = UserCacheService::getCachedProductPriceBookingDetails(
                ppdid: $productPricesDetailsId
            );
            if ($cachedBookingDetailsResponse->isSuccess()) {
                Logger::debug('Booking details retrieved from cache', [
                    'log_file' => config('logging.log_files.products'),
                    'ppdid' => $productPricesDetailsId,
                    'action' => 'booking_details_cache_hit',
                ]);
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
            Logger::debug('Booking details retrieved from API', [
                'log_file' => config('logging.log_files.products'),
                'ppdid' => $productPricesDetailsId,
                'action' => 'booking_details_api_success',
            ]);
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            Logger::error('Failed to retrieve booking details', data: [
                'log_file' => config('logging.log_files.products'),
                'ppdid' => $productPricesDetailsId,
                'action' => 'booking_details_api_failed',
            ]);
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
        Logger::debug('Getting product availabilities from API', [
            'log_file' => config('logging.log_files.products'),
            'ppdid' => $productPricesDetailsId,
            'time_id' => $timeId,
            'start_date' => $startDate,
            'days' => $days,
            'action' => 'get_availabilities_api_start',
        ]);

        $requestUrl = config('vars.tdms_api_url')
                    . "/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/$days";
        $response = Http::withToken($agentToken)
            ->withHeaders([
                "Authorization: Bearer {$agentToken}",
                'Content-Type' => 'application/json',
            ])
            ->get($requestUrl);
        if ($response->successful()) {
            Logger::debug('Product availabilities retrieved', [
                'log_file' => config('logging.log_files.products'),
                'ppdid' => $productPricesDetailsId,
                'time_id' => $timeId,
                'start_date' => $startDate,
                'action' => 'get_availabilities_api_success',
            ]);
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            Logger::error('Failed to retrieve availability', data: [
                'log_file' => config('logging.log_files.products'),
                'ppdid' => $productPricesDetailsId,
                'time_id' => $timeId,
                'start_date' => $startDate,
                'action' => 'get_availabilities_api_failed',
            ]);
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
        Logger::debug('Getting product availabilities by range', [
            'log_file' => config('logging.log_files.products'),
            'product_id' => $productId,
            'fare_type_id' => $fareTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'action' => 'get_availabilities_by_range_start',
        ]);

        $requestUrl = config('vars.tdms_api_url')
                    . "/checkAvailabilityByProductAndRange/{$productId}/{$fareTypeId}/{$startDate}/{$endDate}";

        $response = Http::withToken($agentToken)
            ->withHeaders([
                "Authorization: Bearer {$agentToken}",
                'Content-Type' => 'application/json',
            ])
            ->post($requestUrl);

        if ($response->successful()) {
            Logger::debug('Product availabilities by range retrieved', [
                'log_file' => config('logging.log_files.products'),
                'product_id' => $productId,
                'fare_type_id' => $fareTypeId,
                'action' => 'get_availabilities_by_range_success',
            ]);
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status(),
            );
        } else {
            Logger::error('Failed to retrieve availability by range', data: [
                'log_file' => config('logging.log_files.products'),
                'product_id' => $productId,
                'fare_type_id' => $fareTypeId,
                'action' => 'get_availabilities_by_range_failed',
            ]);
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
        Logger::debug('Getting fare price availability', [
            'log_file' => config('logging.log_files.products'),
            'product_id' => $productId,
            'ppdid' => $productPricesDetailsId,
            'booking_date' => $bookingDate,
            'time_id' => $timeId,
            'get_cached' => $getCached,
            'action' => 'get_fareprice_availability_start',
        ]);

        if ($getCached) {
            $cachedAvailabilityResponse = UserCacheService::getCachedProductPriceAvailability(
                ppdid: $productPricesDetailsId,
                bookingDate: $bookingDate,
                timeId: $timeId
            );
            if ($cachedAvailabilityResponse->isSuccess()) {
                Logger::debug('Fare price availability retrieved from cache', [
                    'log_file' => config('logging.log_files.products'),
                    'ppdid' => $productPricesDetailsId,
                    'booking_date' => $bookingDate,
                    'action' => 'fareprice_availability_cache_hit',
                ]);
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
                Logger::debug('Product availability not found (by range)', [
                    'log_file' => config('logging.log_files.products'),
                    'product_id' => $productId,
                    'booking_date' => $bookingDate,
                    'action' => 'fareprice_availability_not_found',
                ]);
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
                Logger::debug('Product availability not found (from API)', [
                    'log_file' => config('logging.log_files.products'),
                    'ppdid' => $productPricesDetailsId,
                    'booking_date' => $bookingDate,
                    'action' => 'fareprice_availability_not_found',
                ]);
                return ServiceResponse::notFound(
                    message: 'Product availability not found',
                );
            }

            $productAvailabilities = $productAvailabilitiesResponse->data;
        }

        if (empty($productAvailabilities)) {
            Logger::debug('Product availability empty', [
                'log_file' => config('logging.log_files.products'),
                'product_id' => $productId,
                'ppdid' => $productPricesDetailsId,
                'action' => 'fareprice_availability_empty',
            ]);
            return ServiceResponse::notFound(
                message: 'Product availability not found',
            );
        }

        Logger::debug('Fare price availability retrieved', [
            'log_file' => config('logging.log_files.products'),
            'product_id' => $productId,
            'ppdid' => $productPricesDetailsId,
            'action' => 'fareprice_availability_success',
        ]);
        return ServiceResponse::success(data: $productAvailabilities[0]);
    }
}
