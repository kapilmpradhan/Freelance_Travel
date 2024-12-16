<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\AgentToken;

class AgentTokenService
{
    public static function getAgentToken(string $username, string $password)
    {
        $url = config('vars.tdms_api_url') . '/agentToken';

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

    public static function getSharedToken()
    {
        try {
            $agent_username = config('vars.default_token_agent_username');
            $agent_password = config('vars.default_token_agent_password');

            $available_shared_token = AgentToken::where('type', 'shared')->first();

            if ($available_shared_token) {
                $current_date_time = Carbon::now();
                $token_last_update = $available_shared_token->updated_at;

                if ($token_last_update->diffInHours($current_date_time) > 21) {
                    $new_token = AgentTokenService::getAgentToken($agent_username, $agent_password);
                    if (!$new_token) {
                        return null;
                    }

                    $available_shared_token->update($new_token);
                    return $available_shared_token;
                }
            }

            return $available_shared_token->access_token;
        } catch (\Exception $e) {
            return null;
        }
    }
}
