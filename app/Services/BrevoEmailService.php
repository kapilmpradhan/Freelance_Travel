<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BrevoEmailService
{
    public function sendMail($data)
    {
        try {
            $client = new Client();

            // Send request to Brevo API
            $response = $client->post('https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => env('BREVO_MAIL_API_KEY'),
                    'content-type' => 'application/json',
                ],
                'json' => $data,
            ]);

            if ($response->getStatusCode() == 201) {
                Log::info('Email sent successfully to: ' . $data['to'][0]['email']);
            } else {
                Log::error('Failed to send email. Error: ' . json_decode($response->getBody())->message);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send email. Error: ' . $e->getMessage());
        }
    }
}
