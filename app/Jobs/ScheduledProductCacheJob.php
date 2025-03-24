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
            $productsLastUpdateDate = ProductService::getProductsLastUpdateFromApi(
                $agentToken,
                $productsInBatch->toArray()
            );
            foreach ($productsInBatch as $tdmsProductId) {
                $tdmsProductLastUpdateDate = $productsLastUpdateDate[$tdmsProductId] ?? null;
                if (!$tdmsProductLastUpdateDate) {
                    Logger::error('Product last update not found. TDMS product ID: ' . $tdmsProductId);
                    continue;
                }

                $product = $allAvailableCachedProducts->where('tdms_product_id', $tdmsProductId)->first();
                if ($product->tdms_product_last_update_date == $productsLastUpdateDate[$tdmsProductId]) {
                    continue;
                } else {
                    $newProductDetails = ProductService::getProductDetails($agentToken, $tdmsProductId);
                    if (!$newProductDetails) {
                        Logger::error('Unable to get product details. TDMS product ID: ' . $tdmsProductId);
                        continue;
                    }
                    CacheProductJob::dispatch(
                        batchNumber: $batchNumber,
                        product: $newProductDetails['results'][0],
                        checkTime: $tdmsProductLastUpdateDate,
                    )->delay(now()->addMinutes($batchNumber * 5));
                }
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
