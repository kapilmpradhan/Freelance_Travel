<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\FirebaseFcmToken;
use Exception;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;
use Throwable;

class FcmService
{
    protected $accessToken;

    public function __construct()
    {
        $this->accessToken = $this->getAccessToken();
    }

    public function getAccessToken(): ?string
    {
        try {
            $client = new GoogleClient();
            $credentials = config('firebase.credentials');

            if (is_string($credentials)) {
                $credentials = json_decode($credentials, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Logger::error('Invalid JSON in Firebase credentials', data: [
                        'log_file' => config('logging.log_files.fcm_subscription'),
                        'action' => 'fcm_credentials_invalid',
                    ]);
                    throw new Exception("Invalid JSON in Firebase credentials.");
                }
            }
            $client->setAuthConfig($credentials);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            Logger::debug('FCM access token retrieved', [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'action' => 'fcm_access_token_retrieved',
            ]);
            return $token['access_token'] ?? null;
        } catch (Exception $e) {
            Logger::error('Failed to get FCM access token', $e, data: [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'action' => 'fcm_access_token_failed',
            ]);
            return null;
        }
    }

    public function subscribeTokensToTopic($topic, array $tokens): HttpResponse|Exception
    {
        $url = "https://iid.googleapis.com/iid/v1:batchAdd";

        Logger::debug('Subscribing tokens to FCM topic', [
            'log_file' => config('logging.log_files.fcm_subscription'),
            'topic' => $topic,
            'tokens_count' => count($tokens),
            'action' => 'fcm_subscribe_start',
        ]);

        $payload = [
            "to" => "/topics/{$topic}",
            "registration_tokens" => $tokens
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'access_token_auth' => 'true'
            ])->post($url, $payload);

            if (!$response->successful()) {
                Logger::error('Error while subscribing to FCM topic', extra: $response->json(), data: [
                    'log_file' => config('logging.log_files.fcm_subscription'),
                    'topic' => $topic,
                    'action' => 'fcm_subscribe_failed',
                ]);
                return HttpResponse::failed(
                    message: 'Error while subscribing',
                    responseCode: $response->status(),
                    data: $response->json()
                );
            }

            Logger::debug('Tokens subscribed to FCM topic', [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'topic' => $topic,
                'tokens_count' => count($tokens),
                'action' => 'fcm_subscribe_success',
            ]);
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status()
            );
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error while subscribing to FCM topic',
                data: [
                    'log_file' => config('logging.log_files.fcm_subscription'),
                    'topic' => $topic,
                    'action' => 'fcm_subscribe_exception',
                ],
                exception: $e
            );
            throw $e;
        }
    }

    public function unsubscribeTokensFromTopic($topic, array $tokens): HttpResponse|Exception
    {
        $url = "https://iid.googleapis.com/iid/v1:batchRemove";

        Logger::debug('Unsubscribing tokens from FCM topic', [
            'log_file' => config('logging.log_files.fcm_subscription'),
            'topic' => $topic,
            'tokens_count' => count($tokens),
            'action' => 'fcm_unsubscribe_start',
        ]);

        $payload = [
            "to" => "/topics/{$topic}",
            "registration_tokens" => $tokens
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                'access_token_auth' => 'true'
            ])->post($url, $payload);

            if (!$response->successful()) {
                Logger::error('Error while unsubscribing from FCM topic', extra: $response->json(), data: [
                    'log_file' => config('logging.log_files.fcm_subscription'),
                    'topic' => $topic,
                    'action' => 'fcm_unsubscribe_failed',
                ]);
                return HttpResponse::failed(
                    message: 'Error while unsubscribing',
                    responseCode: $response->status(),
                    data: $response->json()
                );
            }

            Logger::debug('Tokens unsubscribed from FCM topic', [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'topic' => $topic,
                'tokens_count' => count($tokens),
                'action' => 'fcm_unsubscribe_success',
            ]);
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status()
            );
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error while unsubscribing from FCM topic',
                data: [
                    'log_file' => config('logging.log_files.fcm_subscription'),
                    'topic' => $topic,
                    'action' => 'fcm_unsubscribe_exception',
                ],
                exception: $e
            );
            throw $e;
        }
    }

    public function sendNotification($data, $token = null, $topic = null)
    {
        $projectId = config('firebase.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        Logger::debug('Sending FCM notification', [
            'log_file' => config('logging.log_files.notifications'),
            'topic' => $topic,
            'has_token' => $token !== null,
            'action' => 'fcm_send_notification_start',
        ]);

        $payload = [
            'message' => [
                'data' => $data,
            ]
        ];

        if ($token) {
            $payload['message']['token'] = $token;
        } elseif ($topic) {
            $payload['message']['topic'] = $topic;
        } else {
            Logger::debug('FCM notification failed - no token or topic', [
                'log_file' => config('logging.log_files.notifications'),
                'action' => 'fcm_send_notification_no_target',
            ]);
            return ServiceResponse::badRequest('Token or topic required');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                ])->post($url, $payload);

            if (!$response->successful()) {
                FirebaseFcmToken::where('token', $token)->delete();
                Logger::error('Error sending FCM notification', extra: [
                    "payload" => $payload,
                    "response" => $response->json()
                ], data: [
                    'log_file' => config('logging.log_files.notifications'),
                    'topic' => $topic,
                    'action' => 'fcm_send_notification_failed',
                ]);
                return;
            }

            Logger::debug('FCM notification sent', [
                'log_file' => config('logging.log_files.notifications'),
                'topic' => $topic,
                'action' => 'fcm_send_notification_success',
            ]);
            $response = $response->json();
        } catch (Exception $e) {
            Logger::exception('Exception sending FCM notification', data: [
                'log_file' => config('logging.log_files.notifications'),
                'topic' => $topic,
                'action' => 'fcm_send_notification_exception',
            ], exception: $e);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'response' => "sent notification",
        ];
    }
}
