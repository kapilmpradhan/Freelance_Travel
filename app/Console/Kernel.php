<?php

namespace App\Console;

use App\Jobs\DeleteAccountPermanentlyJob;
use App\Jobs\HomeFeedCachedProductUpdate;
use App\Jobs\HomeFeedProductByCategories;
use App\Jobs\SendNotificationToInactiveUsers;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\QUpcomingBookingNotification;
use App\Jobs\SendBookingNotification;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ToggleFeature::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new HomeFeedProductByCategories())->dailyAt('00:00');

        $schedule->job(new QUpcomingBookingNotification())->dailyAt("00:00");

        $schedule->job(new SendBookingNotification())->hourlyAt(10)->when(function () {
            return config('app.mode') == 'normal';
        });

        $schedule->job(new SendBookingNotification())->everyMinute()->when(function () {
            return config('app.mode') == 'test';
        });

        $schedule->job(new DeleteAccountPermanentlyJob())->dailyAt("00:20");

        $schedule->job(new SendNotificationToInactiveUsers())->dailyAt("08:30");

        $schedule->job(new HomeFeedCachedProductUpdate())->dailyAt("01:00");
    }

    /**
     * Register the commands for the application.
     *
     * @return voids
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
