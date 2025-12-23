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
        Logger::debug('Email quote event received', [
            'log_file' => config('logging.log_files.quote'),
            'booking_reference' => $event->bookingReference,
            'intent' => $event->intent,
            'action' => 'email_quote_event_received',
        ]);

        if ($event->intent !== 'email-quote') {
            return;
        }
        SendShareMailJob::dispatch($event->emailData);

        Logger::debug('Quote email job dispatched', [
            'log_file' => config('logging.log_files.quote'),
            'booking_reference' => $event->bookingReference,
            'action' => 'quote_email_dispatched',
        ]);
    }
}
