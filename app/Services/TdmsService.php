<?php

namespace App\Services;

use DateTime;
use App\Services\AgentTokenService;

class TdmsService
{
    public static function getCustomerLastOrder($agent, $customerEmail)
    {
        $agentToken = AgentTokenService::getAgentToken($agent->username, $agent->password);
        $requestUrl = config('vars.tdms_api_url');
        $today = new DateTime();
        $sixMonthBackDate = $today->modify('-6 months')->format('d-m-Y');

        $queryParams = array(
            "since" => $sixMonthBackDate,
            "searchOnlyStatus" => "Order"
        );
        $fullUrl = $requestUrl . '/customerOrderDetail?' . http_build_query($queryParams);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentToken['access_token']}",
            "User-Agent: insomnia/10.0.0",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $response = json_decode($response, true);

        if ($response == false) {
            return null;
        }
        $customerOrders = [];
        foreach ($response as $customerOrder) {
            if ($customerOrder['email'] === $customerEmail) {
                $customerOrders[] = $customerOrder;
            }
        }
        return $customerOrders ? $customerOrders[0] : [];
    }
}
