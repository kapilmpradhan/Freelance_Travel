<?php

namespace App\Services;

use App\DTOs\HomeFeedProductFilter;
use App\Logging\Logger;
use App\Models\Agent;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use Carbon\Carbon;
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
            'Experiences',
            'Destinations',
            'Accommodations',
            'Transports'
        ];

        try {
            $key = Redis::keys('home_feed_product_schema');
            if (empty($key)) {
                $productCategories = ProductCategory::all()
                    ->groupBy('type')
                    ->map(function ($groups, $type) {
                        return [
                            'type' => $type . 's',
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
                    if ($category['type'] === 'Experiences') {
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

    public static function getProductByCategoriesWithLabelV2(HomeFeedProductFilter $productFilter, $isJobRun = false)
    {
        $agent = UserAgentService::getDefaultAgentToken($productFilter->agentBranchCode);
        if ($agent->isError()) {
            return $agent;
        }
        $agentToken = $agent->data['access_token'];
        try {
            $homeFeedSchemaResponse = ProductCategoryService::getProductSchemaByCategoriesWithLabel();
            if ($homeFeedSchemaResponse->isError()) {
                return $homeFeedSchemaResponse;
            }
            $homeFeedSchema = $homeFeedSchemaResponse->data;

            if ($productFilter->filterBy == 'destination') {
                $regionsResponse = TdmsService::getCountryRegions($agentToken, $productFilter->countryId);
                if ($regionsResponse->isError()) {
                    return $regionsResponse;
                }

                $regions = $regionsResponse->data;
                $typeLabels = [];
                foreach ($regions as $region) {
                    $typeLabels[] = [
                        "label" => $region['text'],
                        "regionId" => $region['id']
                    ];
                }
            } else {
                $typeLabels = array_values(array_filter($homeFeedSchema, function ($item) use ($productFilter) {
                    return strtolower($item['type']) === $productFilter->filterBy . 's';
                }))[0]['labels'];
            }

            $key = Redis::keys($productFilter->cacheKey);
            if (!empty($key) && !$isJobRun) {
                $productsByLabels = json_decode(Redis::get($productFilter->cacheKey));
            }

            if (empty($key) || empty($productsByLabels) || $isJobRun) {
                $tdmsProductIdsByLabels = [];
                $numberOfRegions = 10;
                if ($productFilter->filterBy == 'destination') {
                    foreach ($typeLabels as $typeLabel) {
                        $productsResponse = TdmsService::getProductsByRegion(
                            $agentToken,
                            $productFilter->countryId,
                            $typeLabel['regionId']
                        );
                        if ($productsResponse->isError()) {
                            return $productsResponse;
                        }

                        $products = $productsResponse->data;
                        if (empty($products)) {
                            continue;
                        } else {
                            $numberOfRegions -= 1;
                        }
                        foreach ($products as $product) {
                            $productExists = Product::where('tdms_product_id', $product['productId'])->exists();
                            if (!$productExists) {
                                CartItemService::cacheProduct($product, Carbon::now());
                            }
                            $tdmsProductIdsByLabels[$typeLabel['label']][] = $product['productId'];
                        }

                        if ($numberOfRegions < 0) {
                            break;
                        }
                    }
                } else {
                    foreach ($typeLabels as $typeLabel) {
                        $categoriesByLabels = [];
                        foreach ($typeLabel['categories'] as $category) {
                            $categoriesByLabels[$category['category_type']][] = $category['category_id'];
                        }

                        $recordStart = 0;
                        $recordLength = 5;
                        $products = [];
                        while (true) {
                            $productsResponse = TdmsService::getProductsByMultipleCategories(
                                categoriesIdByTypes: $categoriesByLabels,
                                agentToken: $agentToken,
                                countryId: $productFilter->countryId,
                                recordStart: $recordStart,
                                recordsLength: $recordLength
                            );
                            if ($productsResponse->isError()) {
                                return $productsResponse;
                            }

                            $productData = $productsResponse->data;

                            $filteredProducts = array_filter($productData, function ($product) use ($productFilter) {
                                if ($productFilter->filterBy === 'accommodation') {
                                    return $product['productClass'] === 'A';
                                }
                                return $product;
                            });

                            $products = array_merge($products, $filteredProducts);

                            if (count($products) < 5 && count($productData) > 0) {
                                $recordStart += $recordLength;
                            } else {
                                break;
                            }
                        }
                        foreach ($products as $product) {
                            $productExists = Product::where('tdms_product_id', $product['productId'])->exists();
                            if (!$productExists) {
                                CartItemService::cacheProduct($product, Carbon::now());
                            }
                            $tdmsProductIdsByLabels[$typeLabel['label']][] = $product['productId'];
                        }
                    }
                }

                Redis::set($productFilter->cacheKey, json_encode($tdmsProductIdsByLabels));
            }

            if ($isJobRun) {
                return;
            }

            $result = [];
            $productsByLabels = json_decode(Redis::get($productFilter->cacheKey));
            foreach ($productsByLabels as $label => $productIds) {
                $products = Product::whereIn('tdms_product_id', $productIds)->get();

                if ($productFilter->filterBy == 'destination') {
                    $destinationLabel = array_filter($regions, function ($region) use ($label) {
                        return ($region['text'] == $label);
                    });
                    $destinationLabel = reset($destinationLabel);
                    $result[] = [
                        "label_id" => !empty($destinationLabel) ? $destinationLabel['id'] : null,
                        "label" => $label,
                        "products" => $products
                    ];
                } else {
                    $result[] = [
                        "label" => $label,
                        "products" => $products
                    ];
                }
            }
        } catch (Exception $e) {
            Logger::error('Unable to get product by categories', $e);
            throw new ServiceException('Unable to get product by categories');
        }

        return ServiceResponse::success(data: $result);
    }
}
