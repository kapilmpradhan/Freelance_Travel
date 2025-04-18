<?php

namespace App\Http\Controllers\Api;

use App\Services\ProductCategoryService;
use App\Services\ServiceException;
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
