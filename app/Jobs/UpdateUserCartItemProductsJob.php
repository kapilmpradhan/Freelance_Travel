<?php

namespace App\Jobs;

use Exception;
use App\Models\Product;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Services\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUserCartItemProductsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $cartItems = CartItem::where('user_id', $this->userId)->get();
            $cartItemsTdmsProductIds = $cartItems
                                    ->select('tdms_product_id')
                                    ->pluck('tdms_product_id')
                                    ->toArray();

            $tdmsProductIdsToString = implode(',', $cartItemsTdmsProductIds);
            $tdms_products_last_update = ProductService::getProductsLastUpdateFromApi([$tdmsProductIdsToString]);

            if (isset($tdms_products_last_update['errors'])) {
                Logger::error($tdms_products_last_update['message']);
                return;
            }

            foreach ($cartItems as $item) {
                $product = Product::where('tdms_product_id', $item->tdms_product_id)
                                    ->orderBy('version', 'desc')
                                    ->first();
                $productId = $product->tdms_product_id;
                if ($product->tdms_product_last_update_date == $tdms_products_last_update[$productId]) {
                    $product->counter = $product->counter + 1;
                    $product->save();
                    Logger::info('No changes to cache. TDMS product id: ' . $productId);
                    return;
                }

                $productDetailsResponse = ProductService::getProductDetailsFromApi($product);

                if (!$productDetailsResponse || !$productDetailsResponse['results']) {
                    $productDetailsFromTdms = [];
                    Logger::error('Unable to cache product. Product not found. TDMS product id: ' . $productId);
                } else {
                    $productDetailsFromTdms = $productDetailsResponse['results'][0];

                    $data = [
                        "tdms_product_id" => $product->tdms_product_id,
                        "tdms_product_last_update_date" => $tdms_products_last_update[$productId],
                        "json" => $productDetailsFromTdms
                    ];

                    Product::create($data);
                    Logger::info('Product cached. TDMS product id: ' . $productId);
                }
            }
        } catch (Exception $e) {
            Logger::error('Unable to cache cart items', $e->getMessage());
        }
    }
}
