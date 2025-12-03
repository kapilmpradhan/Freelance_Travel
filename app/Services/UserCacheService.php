<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Redis;

class UserCacheService
{
    public const PRODUCTS = 'products';
    public const AVAILABILITIES = 'availabilities';
    public const BOOKING_DETAILS = 'bookingDetails';
    public const BOOKING_REFERENCE = 'bookingReference';
    public const PAYMENT_METHODS = 'paymentMethods';

    public static function getUserCachedData($type)
    {
        try {
            $agentType = App::bound('agentType');
            $session = App::bound('sessionId');
            if (!$agentType && !$session) {
                return [];
            }

            $user = app('agentType')->user;
            $key = $user ? $user->uuid : app('sessionId');

            $cachedUserData = Redis::get("{$key}:{$type}");
            return $cachedUserData ? json_decode($cachedUserData, true) : [];
        } catch (Exception) {
            return [];
        }
    }

    public static function addUserCachedData($type, $data, $expireInSeconds = 600)
    {
        $user = app('agentType')->user;
        $session = App::bound('sessionId');
        if (!$user && !$session) {
            throw new ServiceException('Unauthorized user');
        }

        $key = $user ? $user->uuid : app('sessionId');

        // Atomic save to avoid overwriting
        Redis::watch($key);
        Redis::multi();
        Redis::set("{$key}:{$type}", json_encode($data), 'EX', $expireInSeconds);
        Redis::exec();
    }

    public static function removeUserCachedData($type)
    {
        $agentType = App::bound('agentType');
        $session = App::bound('sessionId');
        if ($agentType || $session) {
            $user = $agentType ? app('agentType')->user : null;

            $key = $user ? $user->uuid : app('sessionId');
            Redis::del("{$key}:{$type}");
        }
    }

    public static function cacheProductLastUpdateDate($tdmsProductId, $lastUpdateData)
    {
        $cachedUserData = self::getUserCachedData(self::PRODUCTS);
        $cachedUserData[$tdmsProductId]['lastUpdateDate'] = $lastUpdateData;

        self::addUserCachedData(self::PRODUCTS, $cachedUserData);
    }

    public static function getCachedProductLastUpdate($tdmsProductId)
    {
        $cachedUserData = self::getUserCachedData(self::PRODUCTS);
        if (isset($cachedUserData[$tdmsProductId]['lastUpdateDate'])) {
            return ServiceResponse::success($cachedUserData[$tdmsProductId]['lastUpdateDate']);
        }

        return ServiceResponse::notFound();
    }

    public static function cacheProduct($tdmsProductId, $productDetailsData)
    {
        $cachedUserData = self::getUserCachedData(self::PRODUCTS);
        $cachedUserData[$tdmsProductId]['details'] = $productDetailsData;

        self::addUserCachedData(self::PRODUCTS, $cachedUserData, 900);
    }

    public static function getCachedProductDetails($tdmsProductId)
    {
        $cachedUserData = self::getUserCachedData(self::PRODUCTS);
        if (isset($cachedUserData[$tdmsProductId]['details'])) {
            return ServiceResponse::success($cachedUserData[$tdmsProductId]['details']);
        }

        return ServiceResponse::notFound();
    }

    public static function getCachedProduct()
    {
        $cachedUserData = self::getUserCachedData(self::PRODUCTS);
        if (!empty($cachedUserData)) {
            return ServiceResponse::success($cachedUserData);
        }

        return ServiceResponse::notFound();
    }

    public static function cacheProductPriceAvailability($ppdid, $bookingDate, $timeId, $availabilityData)
    {
        $cachedUserData = self::getUserCachedData(self::AVAILABILITIES);
        $cachedUserData[$ppdid]["{$bookingDate}_{$timeId}"] = $availabilityData;

        self::addUserCachedData(self::AVAILABILITIES, $cachedUserData, 600);
    }

    public static function getCachedProductPriceAvailability($ppdid, $bookingDate, $timeId)
    {
        $cachedUserData = self::getUserCachedData(self::AVAILABILITIES);
        if (isset($cachedUserData[$ppdid]["{$bookingDate}_{$timeId}"])) {
            return ServiceResponse::success($cachedUserData[$ppdid]["{$bookingDate}_{$timeId}"]);
        }

        return ServiceResponse::notFound();
    }

    public static function cacheProductPriceBookingDetails($ppdid, $bookingDetailsData)
    {
        $cachedUserData = self::getUserCachedData(self::BOOKING_DETAILS);
        $cachedUserData[$ppdid] = $bookingDetailsData;

        self::addUserCachedData(self::BOOKING_DETAILS, $cachedUserData, 300);
    }

    public static function getCachedProductPriceBookingDetails($ppdid)
    {
        $cachedUserData = self::getUserCachedData(self::BOOKING_DETAILS);
        if (isset($cachedUserData[$ppdid])) {
            return ServiceResponse::success($cachedUserData[$ppdid]);
        }

        return ServiceResponse::notFound();
    }

    public static function cacheBookingReference($bookingReferenceData)
    {
        $cachedUserData = self::getUserCachedData(self::BOOKING_REFERENCE);
        $cachedUserData = $bookingReferenceData;

        self::addUserCachedData(self::BOOKING_REFERENCE, $cachedUserData, 900);
    }

    public static function getCachedBookingReference()
    {
        $cachedUserData = self::getUserCachedData(self::BOOKING_REFERENCE);
        if (!empty($cachedUserData)) {
            return ServiceResponse::success($cachedUserData);
        }

        return ServiceResponse::notFound();
    }

    public static function removeCachedBookingReference()
    {
        self::removeUserCachedData(self::BOOKING_REFERENCE);

        return ServiceResponse::success();
    }

    public static function cachePaymentMethods($paymentMethodsData)
    {
        $cachedUserData = self::getUserCachedData(self::PAYMENT_METHODS);
        $cachedUserData = $paymentMethodsData;

        self::addUserCachedData(self::PAYMENT_METHODS, $cachedUserData, 900);
    }

    public static function getCachedPaymentMethods()
    {
        $cachedUserData = self::getUserCachedData(self::PAYMENT_METHODS);
        if (!empty($cachedUserData)) {
            return ServiceResponse::success($cachedUserData);
        }

        return ServiceResponse::notFound();
    }
}
