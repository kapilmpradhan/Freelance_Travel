<?php

namespace App\Providers;

use App\Events\CompleteOrderEvent;
use App\Events\OrderComplete;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\OrderPosted;
use App\Listeners\CleanCartItems;
use App\Listeners\EmailCompleteOrder;
use App\Listeners\EmailQuote;
use App\Listeners\SetBookingNotificationData;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        OrderPosted::class => [
            CleanCartItems::class,
            EmailQuote::class,
        ],
        CompleteOrderEvent::class => [
            EmailCompleteOrder::class,
            SetBookingNotificationData::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
