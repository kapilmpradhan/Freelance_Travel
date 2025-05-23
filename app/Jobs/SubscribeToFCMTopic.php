<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\User;
use App\Services\FcmService;
use App\Services\ServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubscribeToFCMTopic implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $fcmToken;
    public $topic;

    public function __construct($fcmToken, $topic)
    {
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

            if ($subscribeResponse->success()) {
                Logger::info('Successfully subscribed to FCM topic');
            }
            return;
        } catch (ServiceException $e) {
            Logger::error('Error subscribing to FCM topic', exception: $e);
            return;
        }
    }
}
