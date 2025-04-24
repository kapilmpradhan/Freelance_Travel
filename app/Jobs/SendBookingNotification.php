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
use App\Models\User;
use App\Models\UserOrder;
use App\Services\FcmService;
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

    public function getFcmNotificationData($item, $bookingReference)
    {
        $user = User::find($item->user_id);
        $tokens = $user->fcmTokens();

        $data = ['path' => "booking/{$bookingReference}/{$item->tdms_product_id}"];

        $notification = [
            'title' => 'Upcoming Booking Notification',
            'body' => "You have an upcoming booking."
        ];

        $fcmNotificationData = [
            "notification" => $notification,
            "data" => $data
        ];

        if (count($tokens) > 1) {
            $fcmService = new FcmService();
            $fcmService->subscribeTokensToTopic(
                topic: $user->uuid,
                tokens: $tokens
            );

            $fcmNotificationData['token'] = null;
            $fcmNotificationData['topic'] = $user->uuid;
        } else {
            $fcmNotificationData['token'] = $tokens[0];
            $fcmNotificationData['topic'] = null;
        }

        return $fcmNotificationData;
    }

    /**
     * Execute the job.
     */
    public function handle(IEmailService $emailService, FcmService $fcmService)
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
            $fcmNotificationData = [];

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

                    $booking = (clone $bookingNotifications)
                                ->where('id', $notification->booking_notification_id)
                                ->first();

                    $bookingData = [
                        'product_name' => $product->json['name'],
                        'booking_date' => $booking->booking_date,
                        'booking_time' => $notification->booking_time,
                    ];

                    $userOrder = UserOrder::where('id', $notification->user_order_id)->first();
                    $bookingReference = $userOrder->booking_reference;

                    $messageVersion = [
                            'to' => [
                                [
                                    'email' => $notification->notify_to_email,
                                ],
                            ],
                            'subject' => 'Upcoming Booking Notification',
                            'htmlContent' => view('email.bookingNotification', $bookingData)->render(),
                        ];

                    $fcmNotificationData[] = $this->getFcmNotificationData($item, $bookingReference);

                    $messageVersions[] = $messageVersion;
                } catch (Exception $e) {
                    Logger::error('Failed to send booking notification email to' . $notification->notify_to_email, $e);
                }
            }

            try {
                $emailData['messageVersions'] = $messageVersions;
                $emailService->sendMail($emailData);
                foreach ($fcmNotificationData as $data) {
                    $fcmService->sendNotification(
                        token: $data['token'],
                        notification: $data['notification'],
                        data: $data['data'],
                        topic: $data['topic']
                    );
                }

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
