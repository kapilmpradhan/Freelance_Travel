<?php

namespace App\Services;

use App\Logging\Logger;
use Carbon\Carbon;
use App\Models\AgentToken;

class AgentTokenService
{
    public static function getDefaultAgentToken()
    {
        Logger::debug('Fetching default agent token', [
            'log_file' => config('logging.log_files.agent'),
            'action' => 'get_default_agent_token',
        ]);

        // TODO: replace it with AgentDetail
        $default_agent_token = AgentToken::where('type', 'default')->first();

        if ($default_agent_token->updated_at->diffInHours(Carbon::now()) > 21) {
            $new_token = self::getAgentToken(
                username: config('vars.default_token_agent_username'),
                password: config('vars.default_token_agent_password'),
            );
            $default_agent_token->update($new_token);
        }

        return $default_agent_token->access_token;
    }

    public static function getAgentToken(string $username, string $password)
    {
        Logger::debug('Fetching agent token', [
            'log_file' => config('logging.log_files.agent'),
            'username' => $username,
            'action' => 'get_agent_token',
        ]);

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
}
