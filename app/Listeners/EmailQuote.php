<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\OrderPosted;
use App\Jobs\SendShareMailJob;
use App\Logging\Logger;

class EmailQuote implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderPosted $event): void
    {
        $className = get_class($this);
        Logger::debug("[{$className}] Received order created event");
        if ($event->intent !== 'email-quote') {
            Logger::debug("[{$className}] Ignored sending email. Intent: {$event->intent}");
            return;
        }
        SendShareMailJob::dispatch($event->emailData);
        Logger::debug("[{$className}] Dispatched job to send quote email");
    }
}
