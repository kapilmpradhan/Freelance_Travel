<?php

namespace App\Jobs;

use App\Models\BookingNotification;
use App\Models\Product;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\IEmailService;
use App\Logging\Logger;
use App\Models\BookingNotificationDaily;
use App\Models\CartItem;
use App\Services\ServiceException;

class SendBookingNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $userId;

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService)
    {
        $timeNow = now()->format('H:i:s');
        $twoHoursLater = now()->addHours(2)->format('H:i:s');
        $dailyBookingNotification = BookingNotificationDaily::where('is_notified', false)
                                                            ->whereBetween('booking_time', [$timeNow, $twoHoursLater]);

        $notificationIds = (clone $dailyBookingNotification)
                            ->select('booking_notification_id')
                            ->pluck('booking_notification_id');

        $bookingNotifications = BookingNotification::whereIn('id', $notificationIds);

        $batchNumber = 0;
        $batchSize = 20;
        $notifications = $dailyBookingNotification->get();

        $notificationsInBatch = $notifications->slice(
            ($batchNumber * $batchSize),
            ($batchNumber * $batchSize) + $batchSize
        );

        while ($notificationsInBatch->count() > 0) {
            $messageVersions = [];
            $emailData = [
                'sender' => [
                    'email' => config('vars.mail_from_address')
                ],
                'subject' => 'Upcoming Booking Notification',
                'textContent' => "You have an upcoming booking."
                                . " Please check freelance travel.</p></body></html>"
            ];

            foreach ($notificationsInBatch as $notification) {
                try {
                    $itemId = $notification->cart_item_id;
                    $item = CartItem::find($itemId);
                    $productId = $item->tdms_product_id;
                    $product = Product::where('tdms_product_id', $productId)->first();

                    $booking = $bookingNotifications->where('id', $notification->booking_notification_id)->first();

                    $bookingData = [
                        'product_name' => $product->json['name'],
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $notification->booking_time,
                    ];

                    $messageVersion = [
                            'to' => [
                                [
                                    'email' => $notification->notify_to_email,
                                ],
                            ],
                            'subject' => 'Upcoming Booking Notification',
                            'htmlContent' => view('email.bookingNotification', $bookingData)->render(),
                        ];

                    $messageVersions[] = $messageVersion;
                } catch (Exception $e) {
                    Logger::error('Failed to send booking notification email to' . $notification->notify_to_email, $e);
                }
            }

            try {
                $emailData['messageVersions'] = $messageVersions;
                $emailService->sendMail($emailData);

                $notificationIds = $notificationsInBatch->pluck('id');
                $dailyBookingNotification->whereIn('id', $notificationIds)->update(['is_notified' => true]);

                Logger::info('Notification mail sent for ' . $batchNumber . ' batch');
            } catch (ServiceException $e) {
                throw $e;
            }

            $batchNumber += 1;
            $notificationsInBatch = $notifications->slice(
                ($batchNumber * $batchSize),
                ($batchNumber * $batchSize) + $batchSize
            );
        }
    }
}
