<?php

namespace App\Services;

use App\Logging\Logger;

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

    public static function getProductAvailabilitiesFromApi(
        $agentToken,
        $productPricesDetailsId,
        $timeId,
        $startDate,
        $days,
    ) {
        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt(
            $curl,
            CURLOPT_URL,
            "{$requestUrl}/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/$days",
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $response = json_decode($response, true);
        return $response;
    }
}
