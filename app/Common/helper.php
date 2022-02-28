<?php

if (!function_exists("getBookingDetail")) {
    function getBookingDetail($requestUrl, $orderId, $token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/voucher/{$orderId}.json");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        return $result;
    }
}

if (!function_exists("getProductDetail")) {
    function getProductDetail($productId, $token)
    {
        $requestUrl = env('PRODUCTION_URL', 'https://appweb.websitetravel.com/apiv1');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/product/{$productId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        return $result;
    }
}

if (!function_exists("getToken")) {
    function getToken()
    {
        $requestUrl = env('PRODUCTION_URL', 'https://appweb.freelance-travel.com/apiv1');
        $userName = env('PRODUCTION_USER_NAME', 'james.nguyen@adamodigital.com');
        $password = env('PRODUCTION_PASSWORD', 'PEqyHdMUMsvFQ7s');
        $body = [
            'username' => $userName,
            'password' => $password
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/agentToken");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $result = curl_exec($curl);
        return $result;
    }
}
