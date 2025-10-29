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

class UnsubscribeFromFCMTopic implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $fcmToken;
    public $topic;
    public $user;

    public function __construct($user, $fcmToken, $topic)
    {
        $this->fcmToken = $fcmToken;
        $this->topic = $topic;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info("Unsubscribing from FCM topic $this->topic");

        $service = new FcmService();

        try {
            $unsubscribeResponse = $service->unsubscribeTokensFromTopic(
                tokens: [$this->fcmToken],
                topic: $this->topic
            );

            $logData = [
                'log_file' => config('logging.log_files.fcm_subscription'),
                'user_id' => $this->user->uuid,
                'user_email' => $this->user->email,
                'type' => 'unsubscribe',
                'fcm_token' => $this->fcmToken,
                'topic' => $this->topic,
                'response' => json_encode($unsubscribeResponse->data),
            ];

            if ($unsubscribeResponse->success()) {
                Logger::info(
                    message: 'Unsubsribed to FCM topic',
                    data: $logData,
                    write: true
                );
            } else {
                Logger::error(
                    message: 'Error subscribing to FCM topic',
                    data: array_merge($logData, ['response' => $unsubscribeResponse->data]),
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
