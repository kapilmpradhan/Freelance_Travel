<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\Product;
use App\Models\ProductHistory;
use Exception;

class ProductCacheService
{
    public static function cacheProduct($tdmsProductId, $tdms_product_last_update_date, $agentToken)
    {
        $getUpdatedProductDetails = ProductService::getProductDetails($agentToken, $tdmsProductId);
        if (!$getUpdatedProductDetails) {
            Logger::error('Unable to get product details. TDMS product ID: ' . $tdmsProductId);
            return ServiceResponse::notFound('Product not found.');
        }

        $availableCachedProduct = Product::where('tdms_product_id', $tdmsProductId)->first();

        $toCacheData = [
            "version" => $availableCachedProduct->version + 1,
            "tdms_product_last_update_date" => $tdms_product_last_update_date,
            "json" => $getUpdatedProductDetails['results'][0]
        ];

        try {
            ProductHistory::create($availableCachedProduct->toArray());
            $availableCachedProduct->update($toCacheData);
            Logger::info('Product cached.');
            return ServiceResponse::success('Product cached');
        } catch (Exception $e) {
            Logger::error('Failed while caching product. TDMS product ID: ' . $tdmsProductId, $e);
            return ServiceResponse::badRequest('Could not cache product');
        }
    }
}
