<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrevoEmailService implements IEmailService
{
    public function sendMail(array $data): string
    {
        try {
            // Send request to Brevo API
            $response = Http::withHeaders([
                'api-key' => env('BREVO_MAIL_API_KEY'),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', $data);

            if ($response->getStatusCode() == 201) {
                return 'Email sent successfully to: ' . $data['to'][0]['email'];
            } else {
                return 'Failed to send email. Error: ' . json_decode($response->getBody());
            }
        } catch (\Exception $e) {
            return 'Failed to send email. Error: ' . $e->getMessage();
        }
    }
}
