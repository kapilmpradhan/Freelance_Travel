<?php

namespace App\Jobs;

use Exception;
use App\Logging\Logger;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CacheProductJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $product;

    /**
     * Create a new job instance.
     */
    public function __construct($product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $tdmsProductId = $this->product->tdms_product_id;
            $product_exists_in_cache = Product::where('tdms_product_id', $tdmsProductId)
                                    ->orderBy('version', 'desc')
                                    ->first();

            $tdms_product_last_update = ProductService::getProductsLastUpdateFromApi([$tdmsProductId]);

            if ($product_exists_in_cache) {
                $product_last_update = $product_exists_in_cache->tdms_product_last_update_date;
                if ($product_last_update === $tdms_product_last_update[$tdmsProductId]) {
                    Logger::info('No changes to cache. TDMS product id: ' . $product_exists_in_cache->tdms_product_id);
                    return;
                }
            }

            $productDetailsResponse = ProductService::getProductDetailsFromApi($this->product);
            if (!$productDetailsResponse || !$productDetailsResponse['results']) {
                Logger::error('Unable to cache product. Product not found. TDMS product id: ' . $tdmsProductId);
            } else {
                $productDetailsFromTdms = $productDetailsResponse['results'][0];

                $data = [
                    "tdms_product_id" => $tdmsProductId,
                    "tdms_product_last_update_date" => $tdms_product_last_update[$tdmsProductId],
                    "json" => $productDetailsFromTdms
                ];

                Product::create($data);
                Logger::info('Product cached. TDMS product id: ' . $tdmsProductId);
            }
        } catch (Exception $e) {
            Logger::error('Unable to cache product. TDMS product id: ' . $tdmsProductId, $e->getMessage());
        }
    }
}
