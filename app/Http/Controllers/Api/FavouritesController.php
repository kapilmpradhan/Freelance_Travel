<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Services\FavouritesService;
use App\Services\ServiceException;

class FavouritesController extends BaseController
{
    public function addFavouriteProduct(Request $request, $tdmsProductId)
    {
        try {
            $addResponse = FavouritesService::addFavourite($request->user->uuid, $tdmsProductId);
            return $this->sendResponseFromService($addResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function getFavouriteProducts(Request $request)
    {
        $favouriteProducts = FavouritesService::getUserFavouriteProducts($request->user->uuid);
        return $this->sendResponseFromService($favouriteProducts);
    }

    public function removeFavouriteProduct(Request $request, $tdmsProductId)
    {
        try {
            $removeResponse = FavouritesService::removeFavourite($request->user->uuid, $tdmsProductId);
            return $this->sendResponseFromService($removeResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }
}
