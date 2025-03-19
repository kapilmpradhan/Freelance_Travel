<?php

namespace App\Services;

use App\Logging\Logger;
use Kreait\Firebase\Contract\Messaging;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    private $messaging;

    public function __construct(Messaging $messaging)
    {
        $this->messaging = $messaging;
    }

    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $message = $notification->toFcm($notifiable);

        $topicOrToken = $notifiable->routeNotificationFor('fcm');

        if (!$topicOrToken) {
            return; // No topic or token provided
        }

        try {
            // Build the message structure
            $firebaseMessage = [
                'notification' => $message['notification'] ?? [],
                'data' => $message['data'] ?? [],
                'topic' => $topicOrToken,
            ];

            $this->messaging->send($firebaseMessage);
        } catch (\Exception $e) {
            // Handle or log the exception
            Logger::error('Firebase Notification Failed: ' . $e->getMessage());
        }
    }
}
