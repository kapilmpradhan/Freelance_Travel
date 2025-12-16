<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Logging\Logger;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\UserCacheService;
use App\Services\ProductService;
use App\Services\TdmsService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class CacheController extends BaseController
{
    public function cacheLatestProductDetails(Request $request, $tdmsProductId)
    {
        $agent = app('agentType')->agent;

        // Cache product last update and product details (if required)
        $productLastUpdateResponse = ProductService::getProductsLastUpdate(
            agentToken: $agent->access_token,
            productIds: $tdmsProductId
        );
        if ($productLastUpdateResponse->isSuccess()) {
            UserCacheService::cacheProductLastUpdateDate(
                tdmsProductId: $tdmsProductId,
                lastUpdateData: $productLastUpdateResponse->data
            );

            $lastUpdateDate = $productLastUpdateResponse->data[$tdmsProductId];
            $product = Product::firstWhere('tdms_product_id', $tdmsProductId);
            if (
                !$product ||
                $product->tdms_product_last_update_date != $lastUpdateDate
            ) {
                $productDetailsResponse = ProductService::getProductDetailsV2(
                    agentToken: $agent->access_token,
                    productIds: $tdmsProductId,
                    getCached: false
                );
                if ($productDetailsResponse->isSuccess()) {
                    UserCacheService::cacheProduct(
                        tdmsProductId: $tdmsProductId,
                        productDetailsData: $productDetailsResponse->data
                    );
                } else {
                    Logger::error(
                        message: $productDetailsResponse->message,
                        data: $productDetailsResponse->data
                    );
                }
            }
        } else {
            Logger::error(
                message: $productLastUpdateResponse->message,
                data: $productLastUpdateResponse->data
            );
        }

        return $this->sendResponse('Operation successful');
    }

    public function cacheProductPriceDetails(Request $request, $tdmsProductId, $ppdid)
    {
        $agent = app('agentType')->agent;

        $queries = [
            "apiProviderId" => $request->query('apiProviderId'),
            "groupFaresForAvailabilityCheck" => $request->query('groupFaresForAvailabilityCheck'),
            "fareTypeId" => $request->query('fareTypeId'),
            "bookingDate" => $request->query('bookingDate'),
            "timeId" => $request->query('timeId')
        ];

        $validator = Validator::make($queries, [
            "apiProviderId" => "integer|required",
            "groupFaresForAvailabilityCheck" => "boolean|required",
            "fareTypeId" => "integer|required",
            "bookingDate" => "date|required",
            "timeId" => "string|required"
        ]);
        if ($validator->fails()) {
            return $this->sendError(
                title: "Validation error",
                data: $validator->errors()
            );
        }

        $availabilityResponse = ProductService::getFarepriceAvailability(
            agent: $agent,
            productId: $tdmsProductId,
            productPricesDetailsId: $ppdid,
            apiProviderId: $queries['apiProviderId'],
            groupFaresForAvailabilityCheck: $queries['groupFaresForAvailabilityCheck'],
            fareTypeId: $queries['fareTypeId'],
            bookingDate: $queries['bookingDate'],
            timeId: $queries['timeId'],
            getCached: false
        );
        if ($availabilityResponse->isSuccess()) {
            UserCacheService::cacheProductPriceAvailability(
                ppdid: $ppdid,
                bookingDate: $queries['bookingDate'],
                timeId: $queries['timeId'],
                availabilityData: $availabilityResponse->data
            );
        } else {
            Logger::error(
                message: $availabilityResponse->message,
                data: $availabilityResponse->data
            );
        }


        $bookingDetailsResponse = ProductService::getBookingDetails(
            agentToken: $agent->access_token,
            productPricesDetailsId: $ppdid,
            getCached: false
        );
        if ($bookingDetailsResponse->isSuccess()) {
            UserCacheService::cacheProductPriceBookingDetails($ppdid, $bookingDetailsResponse->data);
        } else {
            Logger::error(
                message: $bookingDetailsResponse->message,
                data: $bookingDetailsResponse->data
            );
        }

        return $this->sendResponse('Operation successful');
    }

    public function cacheUserResources(Request $request)
    {
        $user = $request->user;
        $session = App::bound('sessionId');
        if (!$user && !$session) {
            $this->sendError('Unauthorized user');
        }
        $agent = app('agentType')->agent;

        $itemsQ = CartItem::when($user, fn ($query) => $query->where('user_id', $user->uuid))
            ->when(is_null($user) && $session, fn ($query) => $query->where('session_id', app('sessionId')))
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

        return $this->sendResponse('User data cached');
    }
}
