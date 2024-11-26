<?php

namespace App\Services;

class ProductService
{
    public static function getProductDetailsFromApi(int $productId)
    {
        $agentSharedToken = AgentTokenService::getSharedToken();
        if (!$agentSharedToken) {
            echo 'Unable to get shared agent token. Could not fetch product details.'; // TODO: add error reporting
            return;
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

        if ($response == false) {
            return null;
        }
        return json_decode($response, true);
    }
}
