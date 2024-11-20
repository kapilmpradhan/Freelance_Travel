<?php

namespace App\Services;

class AgentTokenService
{
    public static function getAgentToken(string $username, string $password)
    {
        $url = env('TDMS_API_URL') . '/agentToken';

        $data = [
            'username' => $username,
            'password' => $password
        ];

        // Initialize cURL
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $data = json_decode($response, true);

        if (isset($data['statusCode'])) {
            return null;
        }

        return $data;
    }
}
