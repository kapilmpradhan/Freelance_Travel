<?php

namespace App\Providers;

use App\Events\CompleteOrderEvent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\OrderPosted;
use App\Listeners\CleanCartItems;
use App\Listeners\EmailCompleteOrder;
use App\Listeners\EmailQuote;

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
            EmailCompleteOrder::class
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
