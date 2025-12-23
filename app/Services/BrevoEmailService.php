<?php

namespace App\Services;

use App\Enums\AgentBranchCode;
use App\Logging\Logger;
use Exception;
use Illuminate\Support\Facades\Http;

class BrevoEmailService implements IEmailService
{
    public function sendMail(array $data): string
    {
        Logger::debug('Sending email via Brevo', [
            'log_file' => config('logging.log_files.email'),
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'action' => 'brevo_send_mail_start',
        ]);

        try {
            // Send request to Brevo API
            $response = Http::withHeaders([
                'api-key' => config('vars.brevo_mail_api_key'),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', $data);

            if ($response->getStatusCode() == 201) {
                Logger::debug('Email sent successfully via Brevo', [
                    'log_file' => config('logging.log_files.email'),
                    'to' => $data['to'] ?? null,
                    'action' => 'brevo_send_mail_success',
                ]);
                return 'Email sent successfully';
            } else {
                Logger::error(
                    message: 'Failed to send email via Brevo',
                    extra: ['data' => $data, 'response' => $response->json()],
                    data: [
                        'log_file' => config('logging.log_files.email'),
                        'to' => $data['to'] ?? null,
                        'action' => 'brevo_send_mail_failed',
                    ]
                );
                return 'Failed to send email. Error: ' . $response->json();
            }
        } catch (Exception $e) {
            Logger::error('Exception sending email via Brevo', $e, data: [
                'log_file' => config('logging.log_files.email'),
                'to' => $data['to'] ?? null,
                'action' => 'brevo_send_mail_exception',
            ]);
            throw new ServiceException('Failed to send email.');
        }
    }

    public function sendMailV2(array $data): ServiceResponse
    {
        Logger::debug('Sending email V2 via Brevo', [
            'log_file' => config('logging.log_files.email'),
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'action' => 'brevo_send_mail_v2_start',
        ]);

        try {
            // Send request to Brevo API
            $response = Http::withHeaders([
                'api-key' => config('vars.brevo_mail_api_key'),
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])
            ->post('https://api.brevo.com/v3/smtp/email', $data);

            if ($response->getStatusCode() == 201) {
                Logger::debug('Email V2 sent successfully via Brevo', [
                    'log_file' => config('logging.log_files.email'),
                    'to' => $data['to'] ?? null,
                    'action' => 'brevo_send_mail_v2_success',
                ]);
                return ServiceResponse::success(message:'Send Email', data: $response->json());
            } else {
                Logger::error(
                    message: 'Failed to send email V2 via Brevo',
                    extra: ['data' => $data, 'response' => $response->json()],
                    data: [
                        'log_file' => config('logging.log_files.email'),
                        'to' => $data['to'] ?? null,
                        'action' => 'brevo_send_mail_v2_failed',
                    ]
                );
                return ServiceResponse::badRequest('');
            }
        } catch (Exception $e) {
            Logger::error('Exception sending email V2 via Brevo', $e, data: [
                'log_file' => config('logging.log_files.email'),
                'to' => $data['to'] ?? null,
                'action' => 'brevo_send_mail_v2_exception',
            ]);
            throw new ServiceException('Failed to send email.', $e);
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
