<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\UserAgentService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScheduledProductCacheJob implements ShouldQueue
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
        Logger::info('Scheduled product cache job started.');

        // Only get items that are not part of any order
        $allCartItemProductIds = CartItem::where('user_order_id', null)->get()->pluck('tdms_product_id')->toArray();
        $allCartItemProductIds = array_unique($allCartItemProductIds);

        $allAvailableCachedProducts = Product::whereIn('tdms_product_id', $allCartItemProductIds)->get();
        $allAvailableProductUniqueIds = $allAvailableCachedProducts->pluck('tdms_product_id');

        $getDefaultAgentTokenResponse = UserAgentService::getDefaultAgentToken();
        if ($getDefaultAgentTokenResponse->isError()) {
            Logger::error('Unable to update cached products. Error while fetching default agent token.');
            throw new Exception('Unable to update cached products. Error while fetching default agent token.');
        }
        $agentToken = $getDefaultAgentTokenResponse->data['access_token'];

        $batchNumber = 0;
        $batchSize = 10;

        $productsInBatch = $allAvailableProductUniqueIds->slice(
            ($batchNumber * $batchSize),
            ($batchNumber * $batchSize) + $batchSize
        );

        while ($productsInBatch->count() > 0) {
            Logger::info('Processing batch number: ' . $batchNumber);
            foreach ($productsInBatch as $tdmsProductId) {
                $product = $allAvailableCachedProducts->where('tdms_product_id', $tdmsProductId)->first();
                $newProductDetails = ProductService::getProductDetails($agentToken, $tdmsProductId);
                if (!$newProductDetails) {
                    Logger::error('Unable to get product details. TDMS product ID: ' . $tdmsProductId);
                    continue;
                }

                $latestProductDetails = !empty($newProductDetails['results']) ? $newProductDetails['results'][0] : [];
                if (!$product || $product->json != $latestProductDetails) {
                    CacheProductJob::dispatch(
                        product: $allAvailableCachedProducts->where('tdms_product_id', $tdmsProductId)->first(),
                        latestProduct: $latestProductDetails,
                        batchNumber: $batchNumber,
                    )->delay(now()->addMinutes($batchNumber * 5));
                }

                $batchNumber += 1;
                $productsInBatch = $allAvailableProductUniqueIds->slice(
                    ($batchNumber * $batchSize),
                    ($batchNumber * $batchSize) + $batchSize
                );
            }

            Logger::info('Scheduled product cache job completed.');
        }
    }
}
