<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductService;

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
            $item = $item->toArray();
            $tdmsProductId = $item['tdms_product_id'];

            $product = Product::where('tdms_product_id', $tdmsProductId)
                            ->orderBy('version', 'desc')
                            ->first();
            if ($product) {
                $item['version'] = $product->version;
                $item['counter'] = $product->counter;
                $item['details'] = $product->json;
            } else {
                $item['version'] = 0;
                $item['counter'] = 0;
                $item['details'] = null;
            }
            $itemsWithProductDetails[] = $item;
        }

        return $itemsWithProductDetails;
    }
}
