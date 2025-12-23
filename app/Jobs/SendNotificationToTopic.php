<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationToTopic implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $title;
    protected $description;
    protected $data;
    protected $topic;
    protected $fcmService;

    /**
     * Create a new job instance.
     */
    public function __construct($title, $description, $topic, $data = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->data = $data;
        $this->topic = $topic;
        $this->fcmService = new FcmService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::debug('Sending notification to topic', [
            'log_file' => config('logging.log_files.notifications'),
            'topic' => $this->topic,
            'title' => $this->title,
            'action' => 'notification_to_topic_start',
        ]);

        $data = [
            'title' => $this->title,
            'body' => $this->description,
            'data' => $this->data ?? null
        ];

        $this->fcmService->sendNotification(
            topic: $this->topic,
            data: $data,
        );

        Logger::debug('Notification sent to topic', [
            'log_file' => config('logging.log_files.notifications'),
            'topic' => $this->topic,
            'action' => 'notification_to_topic_sent',
        ]);

        return;
    }
}
