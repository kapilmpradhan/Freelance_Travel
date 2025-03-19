<?php

namespace App\Notifications;

use App\Services\FirebaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PushNotification extends Notification
{
    use Queueable;

    private $title;
    private $body;
    private $user;

    public function __construct($title, $body, $user)
    {
        $this->title = $title;
        $this->body = $body;
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return [FirebaseChannel::class];
    }

    public function toFcm($notifiable)
    {
        return [
            'notification' => [
                'title' => $this->title,
                'body' => $this->body,
            ],
            'data' => [
                'key' => 'value',
            ]
        ];
    }
}
