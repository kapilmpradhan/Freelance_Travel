<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\Favourites;
use App\Models\Product;
use App\Models\UserAgent;
use Exception;

class FavouritesService
{
    public static function getUserFavouriteProducts($userId)
    {
        $favourites = Favourites::where('user_id', $userId)
                        ->get();

        $favouiteProductIds = $favourites->pluck('tdms_product_id')->toArray();
        $products = Product::whereIn('tdms_product_id', $favouiteProductIds)->get();

        foreach ($favourites as $favourite) {
            $product = $products->where('tdms_product_id', $favourite->tdms_product_id)->first();
            $product->created_at = $favourite->created_at;
            $product->updated_at = $favourite->updated_at;
        }

        return ServiceResponse::success($products);
    }

    public static function addFavourite($userId, $tdmsProductId)
    {
        $productExists = Product::where('tdms_product_id', $tdmsProductId)->exists();
        if (!$productExists) {
            $agentToken = UserAgentService::getDefaultAgentToken()->data['access_token'] ?? null;
            $productDetailsFromTdms = ProductService::getProductDetails(
                agentToken: $agentToken,
                productId: $tdmsProductId
            );

            if ($productDetailsFromTdms === null || empty($productDetailsFromTdms['results'])) {
                return  ServiceResponse::notFound("Product with ID {$tdmsProductId} does not exist.");
            }

            CartItemService::cacheProduct(
                product: $productDetailsFromTdms['results'][0],
                checkTime: now()
            );
        }

        try {
            $favourite = Favourites::createOrFirst([
                'user_id' => $userId,
                'tdms_product_id' => $tdmsProductId,
            ]);
            return ServiceResponse::success($favourite, 'Added to favourites');
        } catch (Exception $e) {
            Logger::error("Failed to add favourite for user {$userId} and product {$tdmsProductId}: ", $e);
            throw new ServiceException("Failed to add favourite");
        }
    }

    public static function removeFavourite($userId, $tdmsProductId)
    {
        try {
            $favourite = Favourites::where('user_id', $userId)
                ->where('tdms_product_id', $tdmsProductId)
                ->first();
            if (!$favourite) {
                return ServiceResponse::notFound("Product does not exist in favourites.");
            }
            $favourite->delete();
            return ServiceResponse::success(message: 'Removed from favourites');
        } catch (Exception $e) {
            Logger::error("Failed to remove favourite for user {$userId} and product {$tdmsProductId}: ", $e);
            throw new ServiceException("Failed to remove favourite");
        }
    }
}
