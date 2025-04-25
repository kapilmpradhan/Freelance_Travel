<?php

namespace App\Http\Controllers\Api;

use App\DTOs\HomeFeedProductFilter;
use App\Services\ProductCategoryService;
use App\Services\ServiceException;
use App\Services\ServiceResponse;
use Illuminate\Http\Request;

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
