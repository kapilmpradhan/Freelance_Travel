<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TdmsService
{
    public static function getCustomerLastOrderBranch($customerEmail)
    {
        $url = config('vars.tdms_api_url') . "/customerOrders?email={$customerEmail}";

        // Credentials from config
        $username = config('vars.default_token_agent_username');
        $password = config('vars.default_token_agent_password');

        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $data = $response->json();
            if ($data['orders']) {
                return substr(end($data['orders'])['bookingReference'], 0, 3);
            }
        }
        return null;
    }
}
