<?php

namespace App\Jobs;

use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\TdmsService;
use App\Services\UserCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class CacheUserResourcesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $user;
    public $session;
    public $agent;
    public $agentType;
    public function __construct($agentType, $user = null, $session = null)
    {
        $this->user = $user;
        $this->session = $session;
        $this->agent = $agentType->agent;
        $this->agentType = $agentType;
    }

    /**
     * Execute the job.
    */
    public function handle(): void
    {
        App::instance('agentType', $this->agentType);

        $user = $this->user;
        $session = $this->session;
        $agent = $this->agent;

        $itemsQ = CartItem::when($user, fn ($query) => $query->where('user_id', $user->uuid))
            ->when(is_null($user) && $session, fn ($query) => $query->where('session_id', $session))
            ->where('is_direct_purchase', false)
            ->whereNull('user_order_id');

        $tdmsProductIds = (clone $itemsQ)->get()
            ->unique('tdms_product_id')
            ->pluck('tdms_product_id')
            ->toArray();

        $productsQ = Product::whereIn('tdms_product_id', $tdmsProductIds);

        $isProductCacheExpired = empty(UserCacheService::getUserCachedData(UserCacheService::PRODUCTS));
        if ($isProductCacheExpired == true) {
            // Cache latest product details (if required) and last update date of that product
            $productsLastUpdateResponse = ProductService::getProductsLastUpdate(
                agentToken: $agent->access_token,
                productIds: $tdmsProductIds,
                getCached: false
            );
            if ($productsLastUpdateResponse->isSuccess()) {
                $productDetailsResponse = ProductService::getProductDetailsV2(
                    agentToken: $agent->access_token,
                    productIds: $tdmsProductIds,
                    getCached: false
                );
                if ($productDetailsResponse->isError()) {
                    $productsDetailsData = [];
                }
                $productsDetailsData = $productDetailsResponse->data;
                $productsLastUpdate = $productsLastUpdateResponse->data;

                foreach ($productsLastUpdate as $tdmsProductId => $lastUpdate) {
                    UserCacheService::cacheProductLastUpdateDate(
                        tdmsProductId: $tdmsProductId,
                        lastUpdateData: [$tdmsProductId => $lastUpdate]
                    );

                    $latestProducDetails = null;
                    $product = (clone $productsQ)->firstWhere('tdms_product_id', $tdmsProductId);
                    if (!$product || $product->tdms_product_last_update_date != $lastUpdate) {
                        $latestProducDetails = null;
                        foreach ($productsDetailsData as $pdd) {
                            if ($pdd['productId'] == $tdmsProductId) {
                                $latestProducDetails = $pdd;
                                break;
                            }
                        }
                        if (is_null($latestProducDetails)) {
                            continue;
                        }
                    }
                    UserCacheService::cacheProduct(
                        tdmsProductId: $tdmsProductId,
                        productDetailsData: !empty($latestProducDetails) ? $latestProducDetails : null
                    );
                }
            }
        }

        // Cache latest availability of fareprices
        foreach ((clone $itemsQ)->get() as $item) {
            $product = (clone $productsQ)->firstWhere('tdms_product_id', $item->tdms_product_id);
            if (!$product) {
                continue;
            }
            $productData = $product->json;
            $apiProviderId = $productData['apiProviderId'];
            $groupFaresForAvailabilityCheck = $productData['groupFaresForAvailabilityCheck'];

            $fareprice = null;
            foreach ($productData['faresprices'] as $fp) {
                if ($fp['productPricesDetailsId'] == $item->product_price_details_id) {
                    $fareprice = $fp;
                    break;
                }
            }
            if (is_null($fareprice)) {
                continue;
            }

            $farepriceAvailabilityResponse = ProductService::getFarepriceAvailability(
                agent: $agent,
                productId: $item->tdms_product_id,
                productPricesDetailsId: $item->product_price_details_id,
                apiProviderId: $apiProviderId,
                groupFaresForAvailabilityCheck: $groupFaresForAvailabilityCheck,
                fareTypeId: $fareprice['fareTypeId'],
                bookingDate: $item->booking_date,
                timeId: $item->time_id,
                getCached: false
            );

            if ($farepriceAvailabilityResponse->isError()) {
                continue;
            }

            UserCacheService::cacheProductPriceAvailability(
                ppdid: $item->product_price_details_id,
                bookingDate: $item->booking_date,
                timeId: $item->time_id,
                availabilityData: $farepriceAvailabilityResponse->data
            );
        }

        $isBookingRefCacheExpired = empty(UserCacheService::getUserCachedData(UserCacheService::BOOKING_REFERENCE));
        if ($isBookingRefCacheExpired == true) {
            // Cache bookingReference
            $bookingReference = TdmsService::getBookingRefrence(
                agentToken: $agent->access_token,
                getCached: false
            );
            if (!is_null($bookingReference)) {
                UserCacheService::cacheBookingReference(bookingReferenceData: $bookingReference);
            }
        }

        $isPaymentMethodsCacheExpired = empty(UserCacheService::getUserCachedData(UserCacheService::PAYMENT_METHODS));
        if ($isPaymentMethodsCacheExpired == true) {
            // Cache payment methods
            $paymentMethods = TdmsService::getPaymentMethods(
                agentToken: $agent->access_token,
                getCached: false
            );
            if (!is_null($paymentMethods)) {
                UserCacheService::cachePaymentMethods(paymentMethodsData: $paymentMethods);
            }
        }
    }
}
