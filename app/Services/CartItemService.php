<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductPriceAvailability;

class CartItemService
{
    public static function getUserCartItemsWithProductDetails($userId)
    {
        $cartItems = CartItem::where('user_id', $userId)
                                ->select('id', 'tdms_product_id', 'product_price_details_id', 'booking_date', 'time_id')
                                ->get();

        $itemsWithProductDetails = [];
        if (!$cartItems) {
            return $itemsWithProductDetails;
        }

        foreach ($cartItems as $item) {
            $productPriceAvailability = ProductPriceAvailability::where('tdms_product_id', $item->tdms_product_id)
                                            ->where('product_price_details_id', $item->product_price_details_id)
                                            ->first();
            $item = $item->toArray();
            $tdmsProductId = $item['tdms_product_id'];

            $product = Product::where('tdms_product_id', $tdmsProductId)
                            ->orderBy('version', 'desc')
                            ->first();
            if ($product) {
                $item['version'] = $product->version;
                $item['counter'] = $product->counter;
                $item['details'] = $product->json;
                $item['details']['availability'] = $productPriceAvailability
                                                ? $productPriceAvailability->toArray()
                                                : [];
            } else {
                $item['version'] = 0;
                $item['counter'] = 0;
                $item['details'] = null;
            }
            $itemsWithProductDetails[] = $item;
        }

        return $itemsWithProductDetails;
    }

    public static function getCartItemProductAvailability($item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;
        $timeId = $item->time_id;
        $startDate = $item->booking_date;

        $agentSharedToken = AgentTokenService::getSharedToken();
        if (!$agentSharedToken) {
            Logger::error('Agent shared token expired');
            return;
        }

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt(
            $curl,
            CURLOPT_URL,
            "{$requestUrl}/checkavailabilityrange/{$productPricesDetailsId}/{$timeId}/{$startDate}/1"
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentSharedToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (isset($result['errors'])) {
            return null;
        }
        return $result[0];
    }

    public static function getCartItemBookingDetails($item)
    {
        if (!$item) {
            return null;
        }

        $productPricesDetailsId = $item->product_price_details_id;
        $timeId = $item->time_id;
        $startDate = $item->booking_date;

        $agentSharedToken = AgentTokenService::getSharedToken();
        if (!$agentSharedToken) {
            Logger::error('Agent shared token expired');
            return;
        }

        $requestUrl = config('vars.tdms_api_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "{$requestUrl}/bookingdetails/{$productPricesDetailsId}");
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer {$agentSharedToken}",
            "Content-Type: application/json",
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $response = curl_exec($curl);
        $result = json_decode($response, true);

        if (isset($result['errors'])) {
            return null;
        }
        return $result;
    }
}
