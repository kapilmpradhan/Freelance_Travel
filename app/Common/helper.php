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
