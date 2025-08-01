<?php

namespace App\Services;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use Illuminate\Support\Facades\Http;

class BrevoEmailService implements IEmailService
{
    public function sendMail(array $data): string
    {
        try {
            // Send request to Brevo API
            $response = Http::withHeaders([
                'api-key' => config('vars.brevo_mail_api_key'),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', $data);

            if ($response->getStatusCode() == 201) {
                return 'Email sent successfully';
            } else {
                Logger::error('Failed to send email.', extra: ['data' => $data, 'response' => $response->json()]);
                return 'Failed to send email. Error: ' . $response->json();
            }
        } catch (\Exception $e) {
            Logger::error('Failed to send email. Error: ', $e);
            throw new ServiceException('Failed to send email.');
        }
    }

    public static function platformSenderDetail($platform)
    {
        if ($platform == AgentBranchCode::DEFAULT) {
            $mailFromName = config('vars.mail_from_name');
            $mailFromAddress = config('vars.mail_from_address');
        } elseif ($platform == AgentBranchCode::PETERPANS) {
            $mailFromName = config('vars.peterpans_mail_from_name');
            $mailFromAddress = config('vars.peterpans_mail_from_address');
        }

        return [
            'name' => $mailFromName,
            'email' => $mailFromAddress
        ];
    }
}
