<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SubscribeToFCMTopic implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $user;
    public $fcmToken;
    public $topic;

    public function __construct($user, $fcmToken, $topic)
    {
        $this->user = $user;
        $this->fcmToken = $fcmToken;
        $this->topic = $topic;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info("Subscribing to FCM topic '$this->topic'");

        $service = new FcmService();

        try {
            $subscribeResponse = $service->subscribeTokensToTopic(
                tokens: [$this->fcmToken],
                topic: $this->topic
            );

            $logData = [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'user_id' => $this->user->uuid,
                'user_email' => $this->user->email,
                'type' => 'subscribe',
                'fcm_token' => $this->fcmToken,
                'topic' => $this->topic,
                'response' => json_encode($subscribeResponse->data),
            ];

            if ($subscribeResponse->success()) {
                Logger::info(
                    message: 'Subscribed to FCM topic',
                    data: $logData,
                    write: true
                );
            } else {
                Logger::error(
                    message: 'Error subscribing to FCM topic',
                    data: array_merge($logData, ['response' => $subscribeResponse->data]),
                    write: true
                );
            }
            return;
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error subscribing to FCM topic',
                data: $logData ?? [],
                exception: $e,
                write: true
            );
            return;
        }
    }
}
