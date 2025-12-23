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
        $service = new FcmService();

        $logData = [
            'log_file' => config('logging.log_files.fcm_subscription'),
            'user_id' => $this->user->uuid,
            'user_email' => $this->user->email,
            'type' => 'unsubscribe',
            'fcm_token' => $this->fcmToken,
            'topic' => $this->topic,
        ];

        try {
            $unsubscribeResponse = $service->unsubscribeTokensFromTopic(
                tokens: [$this->fcmToken],
                topic: $this->topic
            );

            $logData['response'] = json_encode($unsubscribeResponse->data);

            if ($unsubscribeResponse->success()) {
                Logger::debug('Unsubscribed from FCM topic', $logData);
            } else {
                Logger::error(
                    message: 'Error unsubscribing from FCM topic',
                    data: $logData
                );
            }
            return;
        } catch (Throwable $e) {
            Logger::exception(
                message: 'Error unsubscribing from FCM topic',
                data: $logData,
                exception: $e
            );
            return;
        }
    }
}
