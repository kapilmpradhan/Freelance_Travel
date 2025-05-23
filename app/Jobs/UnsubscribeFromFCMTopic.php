<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\FcmService;
use App\Services\ServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UnsubscribeFromFCMTopic implements ShouldQueue
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
        Logger::info("Unsubscribing from FCM topic $this->topic");

        $service = new FcmService();

        try {
            $unsubscribeResponse = $service->unsubscribeTokensFromTopic(
                tokens: [$this->fcmToken],
                topic: $this->topic
            );

            if ($unsubscribeResponse->isError()) {
                Logger::error('Error unsubscribing from FCM topic');
                return;
            }

            Logger::info('Successfully unsubscribed from FCM topic');
        } catch (ServiceException $e) {
            Logger::error('Error unsubscribing from FCM topic', exception: $e);
            return;
        }
    }
}
