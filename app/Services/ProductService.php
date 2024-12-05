<?php

namespace App\Services;

use App\Logging\Logger;

class ProductService
{
    public static function getProductsLastUpdateFromApi($productIds)
    {
        $productIdsArrayToString = implode(',', $productIds);
        $agentSharedToken = AgentTokenService::getSharedToken();
        if (!$agentSharedToken) {
            Logger::error('Agent shared token expired');
            return;
        }

        $requestUrl = env('TDMS_API_URL');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/lastupdate/{$productIdsArrayToString}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentSharedToken}",
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

    public static function getProductDetailsFromApi($product)
    {
        $productId = $product->tdms_product_id;

        $agentSharedToken = AgentTokenService::getSharedToken();
        if (!$agentSharedToken) {
            Logger::error('Agent shared token expired');
            return null;
        }

        $requestUrl = env('TDMS_API_URL');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/{$productId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentSharedToken}",
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
}
