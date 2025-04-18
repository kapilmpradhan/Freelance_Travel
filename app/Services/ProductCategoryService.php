<?php

namespace App\Services;

use App\Logging\Logger;
use App\Models\Product;
use App\Models\ProductCategory;
use Exception;
use Illuminate\Support\Facades\Redis;

class ProductCategoryService
{
    public static $experienceOrder = [
        'Tours',
        'Adventures',
        'Hiking',
        'Nature',
        'Indigenous Culture',
        'Adrenaline Sports',
        'Sport Related',
        'Water Sports',
        'Health & Wellness',
        'Ice / Snow Activity',
        'Food / Drink Related',
        'Flights',
        'Hire Options',
    ];

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

                $productsByCategories = ProductCategory::where('label', $label)
                            ->get()
                            ->groupBy('label')
                            ->map(function ($groups) use ($label, $members) {
                                foreach ($groups as $group) {
                                    $products = Product::whereIn(
                                        'tdms_product_id',
                                        $members
                                    )->take(10)->get();

                                    $categories = ProductCategory::where('label', $label)->pluck('category');

                                    return [
                                            "labels" => $categories,
                                            "products" => $products
                                        ];
                                }
                            });
                $result[] = $productsByCategories;
            }
        } catch (Exception $e) {
            Logger::error('Unable to get product by categories', $e);
            throw new ServiceException('Unable to get product by categories');
        }

        return ServiceResponse::success(data: $result);
    }

    public static function getProductSchemaByCategoriesWithLabel()
    {
        $resultOrder = [
            'Experience',
            'Destination',
            'Accommodation',
            'Transport'
        ];

        try {
            $key = Redis::keys('home_feed_product_schema');
            if (empty($key)) {
                $productCategories = ProductCategory::all()
                    ->groupBy('type')
                    ->map(function ($groups, $type) {
                        return [
                            'type' => $type,
                            'labels' => $groups->whereNotNull('category_id')
                                ->groupBy('label')
                                ->map(function ($groupedItems, $label) {
                                    return [
                                        'label' => $label,
                                        'categories' => $groupedItems->map(function ($item) {
                                            return [
                                                'category_id' => $item->category_id,
                                                'category_type' => $item->category_type,
                                                'category' => $item->category,
                                            ];
                                        })->values()->toArray(),
                                    ];
                                })->values()->toArray(),
                        ];
                    })->values();

                // Add missing types with empty labels
                // TODO: Update this to use a more efficient method
                foreach ($resultOrder as $type) {
                    if (!$productCategories->contains('type', $type)) {
                        $productCategories->push([
                            'type' => $type,
                            'labels' => [] // Empty array for missing types
                        ]);
                    }
                }

                // Sort the product categories based on the specified order
                $productCategories = $productCategories->values()
                        ->sortBy(function ($type) use ($resultOrder) {
                            return array_search($type['type'], $resultOrder);
                        })->values();

                // Sort the labels for 'Experience' type
                $productCategories = $productCategories->map(function ($category) {
                    if ($category['type'] === 'Experience') {
                        $sortedLabels = [];

                        foreach (self::$experienceOrder as $experience) {
                            foreach ($category['labels'] as $label) {
                                if ($experience === $label['label']) {
                                    $sortedLabels[] = $label;
                                    break;
                                }
                            }
                        }

                        $category['labels'] = $sortedLabels;
                    }

                    return $category;
                });

                Redis::set('home_feed_product_schema', json_encode($productCategories));

                return ServiceResponse::success(
                    message: 'Product categories',
                    data: $productCategories ?? []
                );
            }

            $cachedSchema = Redis::get('home_feed_product_schema');
            if ($cachedSchema) {
                $productCategories = json_decode($cachedSchema, true);
                return ServiceResponse::success(
                    message: 'Product categories',
                    data: $productCategories
                );
            } else {
                return ServiceResponse::notFound(
                    message: 'Product categories not found in cache'
                );
            }
        } catch (Exception $e) {
            Logger::error('Unable to get product by categories', $e);
            throw new ServiceException('Unable to get product by categories');
        }
    }
}
