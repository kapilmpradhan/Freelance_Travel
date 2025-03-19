<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BookingNotification;
use App\Services\BookingNotificationService;
use Carbon\Carbon;

class QUpcomingBookingNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $dates = [Carbon::now()->toDateString(), Carbon::now()->addDays(5)->toDateString()];
        $bookingNotifications = BookingNotification::where('is_completed', false)
                                ->whereBetween('booking_date', $dates)
                                ->get();

        foreach ($bookingNotifications as $bookingNotification) {
            $isNotificationForToday = BookingNotificationService::checkIfBookingNotificationToBeSentToday(
                cartItem: $bookingNotification
            );

            if ($isNotificationForToday) {
                BookingNotificationService::setBookingNotificationDaily($bookingNotification);
            }
        }
    }
}
