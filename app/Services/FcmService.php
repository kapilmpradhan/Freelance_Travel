<?php

namespace App\Services;

use App\Logging\Logger;
use Exception;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

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

    public function subscribeTokensToTopic($topic, array $tokens)
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
                return HttpResponse::failed('Error while subscribing', $response->status());
            }
            return HttpResponse::success($response->json());
        } catch (Exception $e) {
            Logger::error('Error while subsribing', $e);
            return HttpResponse::failed('Error while subsribing', 500);
        }
    }

    public function unsubscribeTokensFromTopic($topic, array $tokens)
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
                return HttpResponse::failed('Error while un-subscribing', $response->status());
            }
            return HttpResponse::success($response->json());
        } catch (Exception $e) {
            Logger::error('Error while un-subscribing', $e);
            return HttpResponse::failed('Error while un-subscribing', 500);
        }
    }

    public function sendNotification($notification, $token = null, $data = null, $topic = null)
    {
        $projectId = config('firebase.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'notification' => $notification,
            ]
        ];

        if ($data) {
            $payload['message']['data'] = $data;
        }

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
