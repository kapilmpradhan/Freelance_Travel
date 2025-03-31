<?php

namespace App\Jobs;

use App\Logging\Logger;
use App\Models\CartItem;
use App\Services\ProductService;
use App\Services\UserAgentService;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateProductSchemaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $schemaVersion;

    public function __construct($schemaVersion)
    {
        $this->schemaVersion = $schemaVersion;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Logger::info('Starting UpdateProductSchemaJob');

        $agentResponse = UserAgentService::getDefaultAgentToken();
        if ($agentResponse->isError()) {
            Logger::error('Unable to retrieve default agent');
            return;
        }
        $agentToken = $agentResponse->data['access_token'];

        $cartItems = CartItem::whereNull('user_order_id')->get();
        $tdmsProductIds = $cartItems->pluck('tdms_product_id')->toArray();
        $products = Product::whereIn('tdms_product_id', $tdmsProductIds)->get();

        $productDetailsFromTdms = ProductService::getMultipleProductDetails($agentToken, $tdmsProductIds);
        if (!$productDetailsFromTdms) {
            Logger::error('Unable to get product details from tdms');
            return;
        }

        foreach ($productDetailsFromTdms['results'] as $productDetails) {
            $product = $products->where('tdms_product_id', $productDetails['productId'])->first();
            if ($product->json == $productDetails && $product->schema_version == $this->schemaVersion) {
                Logger::info("Same schema for {$product->tdms_product_id}");
                continue;
            }

            $product->json = $productDetails;
            $product->schema_version = $this->schemaVersion;
            $product->save();
            Logger::info("Schema updated for {$product->tdms_product_id}");
        }
    }
}
