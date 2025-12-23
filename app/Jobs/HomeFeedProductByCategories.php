<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use App\Services\ServiceException;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class HomeFeedProductByCategories implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::debug('Starting HomeFeedProductByCategories job', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'home_feed_products_job_start',
        ]);

        $agentResponse = UserAgentService::getDefaultAgentToken();
        if ($agentResponse->isError()) {
            Logger::error('Unable to retrieve default agent', data: [
                'log_file' => config('logging.log_files.products'),
                'action' => 'home_feed_products_no_agent',
            ]);
            return;
        }
        $agentToken = $agentResponse->data['access_token'];

        $categoryTypes = ['accommodation', 'transport', 'activities'];

        $tempCategories = ProductCategory::all();
        foreach ($categoryTypes as $type) {
            Logger::debug('Caching products for category type', [
                'log_file' => config('logging.log_files.products'),
                'type' => $type,
                'action' => 'home_feed_products_caching_type',
            ]);
            try {
                $categoriesResponse = TdmsService::getCategoriesByType(type: $type, agentToken: $agentToken);
                if ($categoriesResponse->isError()) {
                    return;
                }
                $categories = $categoriesResponse->data;
                foreach ($categories as $category) {
                    $tempCategory = $tempCategories->where('category', $category['text'])->first();

                    if (!$tempCategory) {
                        continue;
                    }

                    Logger::info("-----{$tempCategory->category}-----");

                    $getProductsResponse = TdmsService::getProductsByCategory($type, $category['id'], $agentToken);
                    if ($getProductsResponse->isError()) {
                        return;
                    }

                    $products = $getProductsResponse->data;
                    if (!$products) {
                        continue;
                    }
                    $productIds = array_column($products, 'productId');
                    $getProductAvailabilities = ProductService::getProductsLastUpdate($agentToken, $productIds, false);
                    if ($getProductAvailabilities->isError()) {
                        Logger::error('Unable to fetch product last update');
                        continue;
                    }

                    foreach ($products as $product) {
                        try {
                            $values = [
                                "json" => $product,
                                "tdms_product_last_update_date" => $getProductAvailabilities
                                                                    ->data[$product['productId']]
                            ];
                            Product::updateOrCreate(
                                [
                                    "tdms_product_id" => $product['productId'],
                                ],
                                $values
                            );
                        } catch (Exception $e) {
                            Logger::error("Failed to cache productId: {$product['productId']}");
                            continue;
                        }

                        // Cache product-to-label mapping
                        $categoryKey = "home_feed_product_label:{$tempCategory->label}";
                        Redis::sadd($categoryKey, $product['productId']);
                    }
                }
            } catch (ServiceException $e) {
                Logger::error('Failed to cache some products', $e, data: [
                    'log_file' => config('logging.log_files.products'),
                    'type' => $type,
                    'action' => 'home_feed_products_cache_error',
                ]);
                continue;
            } catch (Exception $e) {
                Logger::error('Failed to cache some products', $e, data: [
                    'log_file' => config('logging.log_files.products'),
                    'type' => $type,
                    'action' => 'home_feed_products_cache_error',
                ]);
                continue;
            }
            Logger::debug('Caching complete for category type', [
                'log_file' => config('logging.log_files.products'),
                'type' => $type,
                'action' => 'home_feed_products_type_complete',
            ]);
        }
        Logger::debug('HomeFeedProductByCategories job complete', [
            'log_file' => config('logging.log_files.products'),
            'action' => 'home_feed_products_job_complete',
        ]);
        return;
    }
}
