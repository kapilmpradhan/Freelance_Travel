<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\Product;
use App\Models\ProductCategory;
use Exception;
use Illuminate\Support\Facades\Redis;

class ProductCategoryService
{
    public static function getCategories()
    {
        // Fetch all categories that have a non-null category value
        $categories = ProductCategory::whereNotNull('category')
            ->get();

        // Initialize array to store formatted data
        $formattedData = [];
        foreach ($categories as $category) {
            // Create a new array for label if it doesn't exist
            if (!isset($formattedData[$category->label])) {
                $formattedData[$category->label] = [];
            }
            // Add category to the corresponding label array
            $formattedData[$category->label][] = $category->category;
        }

        // Return formatted response with grouped categories
        return ServiceResponse::success(
            message: 'Product categories',
            data: $formattedData
        );
    }

    public static function getProductByCategoriesWithLabel()
    {
        try {
            $keys = Redis::keys('home_feed_product_label:*');
            $result = [];
            foreach ($keys as $key) {
                $parts = explode(':', $key);
                $label = end($parts);
                $members = Redis::smembers($key);
                $result = [];

                $productsByCategories = ProductCategory::all()
                            ->groupBy('label')
                            ->map(function ($groups) use ($label, $members) {
                                $products = Product::whereIn(
                                    'tdms_product_id',
                                    $members
                                )->take(10)->get();

                                $categories = ProductCategory::where('label', $label)->pluck('category');

                                return [
                                        "labels" => $categories,
                                        "products" => $products
                                    ];
                            });
                $result[] = $productsByCategories;
            }
        } catch (Exception $e) {
            Logger::error('Unable to get product by categories', $e);
            throw new ServiceException('Unable to get product by categories');
        }

        return ServiceResponse::success(data: $result);
    }
}
