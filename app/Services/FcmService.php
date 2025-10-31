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
                    throw new Exception("Invalid JSON in Firebase credentials.");
                }
            }
            $client->setAuthConfig($credentials);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();
            return $token['access_token'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function subscribeTokensToTopic($topic, array $tokens): HttpResponse|Exception
    {
        $url = "https://iid.googleapis.com/iid/v1:batchAdd";

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
                Logger::error('Error while subscribing', extra: $response->json());
                return HttpResponse::failed(
                    message: 'Error while subscribing',
                    responseCode: $response->status(),
                    data: $response->json()
                );
            }
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status()
            );
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error while subscribing',
                exception: $e
            );
            throw $e;
        }
    }

    public function unsubscribeTokensFromTopic($topic, array $tokens): HttpResponse|Exception
    {
        $url = "https://iid.googleapis.com/iid/v1:batchRemove";

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
                Logger::error('Error while un-subscribing', extra: $response->json());
                return HttpResponse::failed(
                    message: 'Error while unsubscribing',
                    responseCode: $response->status(),
                    data: $response->json()
                );
            }
            return HttpResponse::success(
                data: $response->json(),
                responseCode: $response->status()
            );
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error while unsubscribing',
                exception: $e
            );
            throw $e;
        }
    }

    public function sendNotification($data, $token = null, $topic = null)
    {
        $projectId = config('firebase.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

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
            return ServiceResponse::badRequest('Token or topic required');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
                ])->post($url, $payload);

            if (!$response->successful()) {
                FirebaseFcmToken::where('token', $token)->delete();
                Logger::error('Error sending fcm notification', extra: [
                    "payload" => $payload,
                    "bearer" => $this->accessToken,
                    "response" => $response->json()
                ]);
                return;
            }
            $response = $response->json();
        } catch (Exception $e) {
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
