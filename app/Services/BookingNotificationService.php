<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\BookingNotification;
use App\Models\BookingNotificationDaily;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class BookingNotificationService
{
    public static function addBookingDataToNotification($cartItems, $userOrder)
    {
        foreach ($cartItems as $cartItem) {
            $userId = $cartItem->user_id;
            $user = User::find($userId);

            $bookingDate = Carbon::parse($cartItem->booking_date)->format('Y-m-d');
            try {
                $rawBookingTime = $cartItem->booking_data['commences'] ?? null;

                if ($rawBookingTime) {
                    // Try parsing the time in both formats
                    $bookingTime = null;

                    // Attempt 12-hour format
                    if (preg_match('/(AM|PM)/i', $rawBookingTime)) {
                        $bookingTime = Carbon::createFromFormat('h:i A', $rawBookingTime)->format('H:i:s');
                    } else {
                        // Assume 24-hour format
                        $bookingTime = Carbon::createFromFormat('H:i', $rawBookingTime)->format('H:i:s');
                    }
                } else {
                    $bookingTime = null;
                }
            } catch (Exception $e) {
                $bookingTime = null;
            }

            Logger::info('Setting up booking notification data for ' . config('vars.test_mode') ? 'test' : 'normal' . 'mode');
            if (config('vars.test_mode')) {
                // Compare booking date with today's date and booking_time is difference of two dates in minutes
                $dateDifferenceFromToday = Carbon::now()->diffInDays(Carbon::parse($cartItem->booking_date));
                $bookingTime = Carbon::now()->addMinutes($dateDifferenceFromToday)->format('H:i:s');

                $bookingNotification = BookingNotification::create([
                    'user_order_id' => $userOrder->id,
                    'cart_item_id' => $cartItem->id,
                    'booking_date' => $bookingDate,
                    'booking_time' => $bookingTime,
                    'notify_to_email' => $user->email,
                    'is_completed' => false
                ]);

                try {
                    self::setBookingNotificationDaily($bookingNotification);
                } catch (ServiceException $e) {
                    throw $e;
                }
            } else {
                $bookingNotification = BookingNotification::create([
                    'user_order_id' => $userOrder->id,
                    'cart_item_id' => $cartItem->id,
                    'booking_date' => $bookingDate,
                    'booking_time' => $bookingTime,
                    'notify_to_email' => $user->email,
                    'is_completed' => false
                ]);

                $isNotifyToday = self::checkIfBookingNotificationToBeSentToday($cartItem);
                if ($isNotifyToday) {
                    try {
                        Logger::info('Adding booking notification to daily table');
                        self::setBookingNotificationDaily($bookingNotification);
                    } catch (ServiceException $e) {
                        throw $e;
                    }
                }
            }
        }

        return ServiceResponse::success();
    }

    public static function checkIfBookingNotificationToBeSentToday($cartItem)
    {
        $diffInDays = Carbon::parse($cartItem->booking_date)->diffInDays(Carbon::now());
        if (in_array($diffInDays, [0, 1, 3, 5])) {
            return true;
        }

        return false;
    }

    public static function setBookingNotificationDaily($bookingNotification)
    {
        try {
            $bookingNotificationDaily = BookingNotificationDaily::updateOrCreate(
                [
                    'cart_item_id' => $bookingNotification->cart_item_id
                ],
                [
                    'booking_notification_id' => $bookingNotification->id,
                    'user_order_id' => $bookingNotification->user_order_id,
                    'booking_date' => $bookingNotification->booking_date,
                    'booking_time' =>  Carbon::parse($bookingNotification->booking_time ?? '7:00')->format('H:i:s'),
                    'notify_to_email' => $bookingNotification->notify_to_email,
                    'is_notified' => false
                ]
            );

            $diffInDays = Carbon::parse($bookingNotification->booking_date)->diffInDays(Carbon::now());
            if ($diffInDays == 0) {
                $bookingNotification->is_completed = true;
                $bookingNotification->save();
            }

            return ServiceResponse::success(data: $bookingNotificationDaily);
        } catch (Exception $e) {
            $errorMessage = "Unable to add booking notification to daily";
            Logger::error(message: $errorMessage, exception: $e);
            throw new ServiceException(message: $errorMessage);
        }
    }
}
