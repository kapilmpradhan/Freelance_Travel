<?php

namespace App\Jobs;

use Exception;
use App\Logging\Logger;
use App\Models\Product;
use App\Models\ProductPriceAvailability;
use App\Services\AgentTokenService;
use App\Services\ProductService;
use App\Services\CartItemService;
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

    protected $user;
    protected $cartItem;

    /**
     * Create a new job instance.
     */
    public function __construct($user, $cartItem)
    {
        $this->user = $user;
        $this->cartItem = $cartItem;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $tdmsProductId = $this->cartItem->tdms_product_id;

            $product_exists_in_cache = Product::where('tdms_product_id', $tdmsProductId)
                                    ->orderBy('version', 'desc')
                                    ->first();
            $tdms_product_id = $this->cartItem->tdms_product_id;
            $productPriceAvailability = ProductPriceAvailability::where('tdms_product_id', $tdms_product_id)
                                        ->where('product_price_details_id', $this->cartItem->product_price_details_id)
                                        ->first();

            $default_agent_access_token = AgentTokenService::getDefaultAgentToken();

            $tdms_product_last_update = ProductService::getProductsLastUpdateFromApi(
                $default_agent_access_token,
                [$tdmsProductId]
            );
            $tdms_product_availability = CartItemService::getCartItemProductAvailability(
                $default_agent_access_token,
                $this->cartItem
            );
            $tdms_product_booking_details = CartItemService::getCartItemBookingDetails(
                $default_agent_access_token,
                $this->cartItem
            );

            if ($productPriceAvailability) {
                $productPriceAvailability->update([
                    'booking_details' => $tdms_product_booking_details,
                    'json' => $tdms_product_availability
                ]);
            } else {
                $productPriceAvailability = ProductPriceAvailability::create([
                    'tdms_product_id' => $this->cartItem->tdms_product_id,
                    'product_price_details_id' => $this->cartItem->product_price_details_id,
                    'booking_details' => $tdms_product_booking_details,
                    'json' => $tdms_product_availability
                ]);
            }

            if (
                $product_exists_in_cache
                && $product_exists_in_cache->tdms_product_last_update_date
                == $tdms_product_last_update[$tdmsProductId]
            ) {
                Logger::info('No changes to cache. TDMS product id: ' . $product_exists_in_cache->tdms_product_id);
                return;
            } else {
                $productDetailsResponse = ProductService::getProductDetailsFromApi(
                    $default_agent_access_token,
                    $this->cartItem
                );
                if (!$productDetailsResponse || !$productDetailsResponse['results']) {
                    Logger::error('Unable to cache product. Product not found. TDMS product id: ' . $tdmsProductId);
                    return;
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
            }
        } catch (Exception $e) {
            Logger::error(
                'Unable to cache product. TDMS product id: '
                . $this->cartItem->tdms_product_id,
                $e->getMessage()
            );
        }
    }
}
