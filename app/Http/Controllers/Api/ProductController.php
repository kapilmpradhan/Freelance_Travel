<?php

namespace App\Http\Controllers\Api;

use App\DTOs\HomeFeedProductFilter;
use App\Jobs\CountryLocationsJob;
use App\Logging\Logger;
use App\Services\ProductCategoryService;
use App\Services\ServiceException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ProductController extends BaseController
{
    public function homeFeedProducts(Request $request)
    {
        try {
            $productResponse = ProductCategoryService::getProductByCategoriesWithLabel();
            return $this->sendResponseFromService($productResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function homeTabLocations(Request $request)
    {
        try {
            $locations = Redis::get('all_countries_states_regions_locations');
            if (!$locations) {
                CountryLocationsJob::dispatch();
                return $this->sendError('Locations data not found');
            }
            $locations = json_decode($locations, true);
            return $this->sendResponse('All countries location', $locations);
        } catch (ServiceException $e) {
            Logger::error('Error fetching home tab locations: ' . $e->getMessage());
            return $this->sendError($e->getMessage(), $e->getCode());
        }
    }

    public function homeFeedProductsV2(Request $request)
    {
        $countryId = $request->query('countryId');
        $filterBy = $request->query('filterBy');

        if (empty($countryId) || empty($filterBy)) {
            return $this->sendError('country and filterBy are required', 400);
        }

        if (!in_array($filterBy, ['experience', 'destination', 'accommodation', 'transport'])) {
            return $this->sendError('filterBy must be one of experience, destination, accomodation, transport');
        }

        $productFilter = HomeFeedProductFilter::fromRequest($request);

        try {
            $productResponse = ProductCategoryService::getProductByCategoriesWithLabelV2($productFilter);
            if ($productResponse->isError()) {
                return $this->sendResponseFromService($productResponse);
            }
            return $this->sendResponseFromService($productResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function homeFeedSchema(Request $request)
    {
        try {
            $productResponse = ProductCategoryService::getProductSchemaByCategoriesWithLabel();
            return $this->sendResponseFromService($productResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }
}
