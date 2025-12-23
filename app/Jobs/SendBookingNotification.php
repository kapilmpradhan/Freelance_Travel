<?php

namespace App\Jobs;

use App\Enums\AgentBranchCode;
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
use App\Services\BrevoEmailService;
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
        if (empty($tokens)) {
            return null;
        }

        $data = [
            'title' => 'Upcoming Booking Notification',
            'body' => "You have an upcoming booking.",
            'path' => "/booking/{$bookingReference}/{$item->tdms_product_id}"
        ];

        $fcmNotificationData = [
            "data" => $data,
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
        if (config('vars.test_mode')) {
            $oneMinutesLater = now()->addMinutes(1)->format('H:i:s');
            $dailyBookingNotification = BookingNotificationDaily::where('is_notified', false)
                                        ->whereBetween('booking_time', [$timeNow, $oneMinutesLater]);
        } else {
            $twoHoursLater = now()->addHours(2)->format('H:i:s');
            $dailyBookingNotification = BookingNotificationDaily::where('is_notified', false)
                                        ->whereBetween('booking_time', [$timeNow, $twoHoursLater]);
        }

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
                'templateId' => (int) config('vars.booking_notification_template_id'),
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

                    $userOrder = UserOrder::where('id', $notification->user_order_id)->first();
                    $bookingReference = $userOrder->booking_reference;

                    $sender = BrevoEmailService::platformSenderDetail($notification->platform);
                    $messageVersion = [
                            'sender' => $sender,
                            'to' => [
                                [
                                    'email' => $notification->notify_to_email,
                                ],
                            ],
                            'templateId' => (int) config('vars.booking_notification_template_id'),
                            'params' => [
                                'productName' => $product->json['name'],
                                'bookingDate' => $booking->booking_date,
                                'bookingTime' => $notification->booking_time,
                                'supportEmail' => $notification->platform == AgentBranchCode::DEFAULT
                                    ? config('vars.mail_from_address')
                                    : config('vars.peterpans_mail_from_address')
                            ]
                        ];

                    $fcmNotificationData[] = $this->getFcmNotificationData($item, $bookingReference);

                    $messageVersions[] = $messageVersion;
                } catch (Exception $e) {
                    Logger::error('Failed to send booking notification', $e, data: [
                        'log_file' => config('logging.log_files.errors'),
                        'notify_to_email' => $notification->notify_to_email,
                        'action' => 'booking_notification_failed',
                    ]);
                }
            }

            try {
                $emailData['messageVersions'] = $messageVersions;
                $emailService->sendMail($emailData);
                if ($fcmNotificationData) {
                    foreach ($fcmNotificationData as $data) {
                        $fcmService->sendNotification(
                            token: $data['token'],
                            data: $data['data'],
                            topic: $data['topic']
                        );
                    }
                }

                $notificationIds = $notificationsInBatch->pluck('id');
                $dailyBookingNotification->whereIn('id', $notificationIds)->update(['is_notified' => true]);

                Logger::debug('Booking notification batch sent', [
                    'log_file' => config('logging.log_files.notifications'),
                    'batch_number' => $batchNumber,
                    'notifications_count' => $notificationsInBatch->count(),
                    'action' => 'booking_notification_batch_sent',
                ]);
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
