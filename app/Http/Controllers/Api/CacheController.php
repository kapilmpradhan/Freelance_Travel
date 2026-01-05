<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Logging\Logger;
use App\Models\Product;
use App\Services\UserCacheService;
use App\Services\ProductService;
use App\Jobs\CacheUserResourcesJob;
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
        $agentType = app('agentType');

        CacheUserResourcesJob::dispatch($agentType, $user, $session);

        return $this->sendResponse('User data cached');
    }
}
