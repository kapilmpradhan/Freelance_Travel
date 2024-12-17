<?php

namespace App\Jobs;

use Exception;
use App\Logging\Logger;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\ProductPriceAvailability;
use App\Services\CartItemService;
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

    protected $agentToken;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($agentToken, $userId)
    {
        $this->agentToken = $agentToken;
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
            $tdms_products_last_update = ProductService::getProductsLastUpdateFromApi(
                $this->agentToken,
                [$tdmsProductIdsToString]
            );

            if (isset($tdms_products_last_update['errors'])) {
                Logger::error($tdms_products_last_update['message']);
                return;
            }

            foreach (array_keys($tdms_products_last_update) as $productId) {
                $product = Product::where('tdms_product_id', $productId)
                                ->orderBy('version', 'desc')
                                ->first();
                if (!$product) {
                    Logger::error('Cannot update. No avaialble cached product');
                    return;
                }
                if (
                    $product->tdms_product_last_update_date
                    == $tdms_products_last_update[$productId]
                ) {
                    $product->counter = $product->counter + 1;
                    $product->save();
                    Logger::info('No changes to cache. TDMS product id: ' . $product->tdms_product_id);
                } else {
                    $productDetailsResponse = ProductService::getProductDetailsFromApi($this->agentToken, $product);
                    if (!$productDetailsResponse || !$productDetailsResponse['results']) {
                        $productDetailsFromTdms = [];
                        Logger::error(
                            'Unable to cache product. Product not found. TDMS product id: '
                            . $productId
                        );
                    } else {
                        $productDetailsFromTdms = $productDetailsResponse['results'][0];
                    }

                    $data = [
                        "tdms_product_id" => $productId,
                        "tdms_product_last_update_date" => $tdms_products_last_update[$productId],
                        "json" => $productDetailsFromTdms
                    ];

                    Product::create($data);
                    Logger::info('Product cached. TDMS product id: ' . $productId);
                }
            }

            foreach ($cartItems as $item) {
                $productPriceAvailability = ProductPriceAvailability::where('tdms_product_id', $item->tdms_product_id)
                                                ->where('product_price_details_id', $item->product_price_details_id)
                                                ->first();

                $tdms_product_availability = CartItemService::getCartItemProductAvailability($this->agentToken, $item);
                $tdms_product_booking_details = CartItemService::getCartItemBookingDetails($this->agentToken, $item);
                if ($productPriceAvailability) {
                    $productPriceAvailability->update([
                        'booking_details' => $tdms_product_booking_details,
                        'json' => $tdms_product_availability
                    ]);
                } else {
                    $productPriceAvailability = ProductPriceAvailability::create([
                        'tdms_product_id' => $item->tdms_product_id,
                        'product_price_details_id' => $item->product_price_details_id,
                        'booking_details' => $tdms_product_booking_details,
                        'json' => $tdms_product_availability
                    ]);
                }
            }
            Logger::info('Product availablity and booking details updated. TDMS product id: ' . $productId);
        } catch (Exception $e) {
            Logger::error('Unable to cache cart items', $e->getMessage());
        }
    }
}
