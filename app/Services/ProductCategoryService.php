<?php

namespace App\Services;

use App\DTOs\HomeFeedProductFilter;
use App\Logging\Logger;
use App\Models\Fareprice;
use App\Models\Product;
use App\Models\ProductCategory;
use Exception;
use Illuminate\Support\Facades\Redis;

class ProductCategoryService
{
    public static $experienceOrder = [
        'Tours',
        'Activity',
        'Attraction',
        'Sail & Cruise',
        'Wildlife & Animals',
        'Food & Drink',
        'Health & Wellness',
        'Culture',
        'Hire'
    ];

    public static function getCategories()
    {
        Logger::debug('Fetching product categories', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'get_categories',
        ]);

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
        Logger::debug('Fetching products by categories with label', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'get_products_by_categories_with_label',
        ]);

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
        Logger::debug('Fetching product schema by categories with label', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'get_product_schema_by_categories_with_label',
        ]);

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
                    data: $productCategories->toArray() ?? []
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
        Logger::debug('Fetching products by categories with label V2', [
            'log_file' => config('logging.log_files.products'),
            'country_id' => $productFilter->countryId,
            'filter_by' => $productFilter->filterBy,
            'is_job_run' => $isJobRun,
            'action' => 'get_products_by_categories_with_label_v2',
        ]);

        $agentResponse = UserAgentService::getUserAgentByBranch($productFilter->agentBranchCode);
        if ($agentResponse->isError()) {
            return $agentResponse;
        }
        $agent = $agentResponse->data;
        try {
            $homeFeedSchemaResponse = ProductCategoryService::getProductSchemaByCategoriesWithLabel();
            if ($homeFeedSchemaResponse->isError()) {
                return $homeFeedSchemaResponse;
            }
            $homeFeedSchema = $homeFeedSchemaResponse->data;

            if ($productFilter->filterBy == 'destination') {
                $regionsResponse = TdmsService::getCountryRegions($agent->access_token, $productFilter->countryId);
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
                            $agent->access_token,
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
                        foreach ($products as $latestProductDetails) {
                            if (!empty($latestProductDetails['departureDates'])) {
                                CartItemService::cacheProductV2(
                                    tdmsProductId: $latestProductDetails['productId'],
                                    agent: $agent,
                                    latestProductDetails: $latestProductDetails
                                );
                                $tdmsProductIdsByLabels[$typeLabel['label']][] = $latestProductDetails['productId'];
                            }
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
                                agentToken: $agent->access_token,
                                countryId: $productFilter->countryId,
                                recordStart: $recordStart,
                                recordsLength: $recordLength
                            );
                            if ($productsResponse->isError()) {
                                return $productsResponse;
                            }

                            $productData = $productsResponse->data;

                            $filteredProducts = array_filter($productData, function ($product) use ($productFilter) {
                                if (!empty($product['departureDates'])) {
                                    if ($productFilter->filterBy === 'accommodation') {
                                        return ($product['productClass'] === 'A');
                                    }
                                    return $product;
                                }
                            });

                            $products = array_merge($products, $filteredProducts);

                            if (count($products) < 5 && count($productData) > 0) {
                                $recordStart += $recordLength;
                            } else {
                                break;
                            }
                        }
                        foreach ($products as $latestProductDetails) {
                            CartItemService::cacheProductV2(
                                tdmsProductId: $latestProductDetails['productId'],
                                agent: $agent,
                                latestProductDetails: $latestProductDetails
                            );
                            $tdmsProductIdsByLabels[$typeLabel['label']][] = $latestProductDetails['productId'];
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
                $productIdsStr = implode(',', array_map(fn ($id) => "'$id'", $productIds));
                $products = Product::whereIn('tdms_product_id', $productIds)
                                ->orderByRaw("FIELD(tdms_product_id, $productIdsStr)")
                                ->get();

                $fareprices = Fareprice::whereIn('tdms_product_id', $productIds)
                    ->where('agent_branch', $agent->branch_code);

                foreach ($products as $product) {
                    $fareprice = (clone $fareprices)->where('tdms_product_id', $product->tdms_product_id)
                        ->where('product_version', $product->version)
                        ->first();
                    if ($fareprice) {
                        $productJson = $product->json;
                        $productJson['faresprices'] = $fareprice->json;
                        $product->json = $productJson;
                        $product->fareprice_branch = $fareprice->agent_branch;
                    } else {
                        $product->fareprice_branch = null;
                    }
                }

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
