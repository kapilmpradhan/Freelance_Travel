<?php

namespace App\Http\Controllers\Api;

use App\Logging\Logger;
use Illuminate\Http\Request;
use App\Services\FavouritesService;
use App\Services\ServiceException;

class FavouritesController extends BaseController
{
    public function addFavouriteProduct(Request $request, $tdmsProductId)
    {
        Logger::debug('Adding favourite product', [
            'log_file' => config('logging.log_files.favourites'),
            'user_id' => $request->user->uuid,
            'tdms_product_id' => $tdmsProductId,
            'action' => 'add_favourite_request',
        ]);

        try {
            $addResponse = FavouritesService::addFavourite($request->user->uuid, $tdmsProductId);
            return $this->sendResponseFromService($addResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }

    public function getFavouriteProducts(Request $request)
    {
        Logger::debug('Getting favourite products', [
            'log_file' => config('logging.log_files.favourites'),
            'user_id' => $request->user->uuid,
            'action' => 'get_favourites_request',
        ]);

        $favouriteProducts = FavouritesService::getUserFavouriteProducts($request->user->uuid);
        return $this->sendResponseFromService($favouriteProducts);
    }

    public function removeFavouriteProduct(Request $request, $tdmsProductId)
    {
        Logger::debug('Removing favourite product', [
            'log_file' => config('logging.log_files.favourites'),
            'user_id' => $request->user->uuid,
            'tdms_product_id' => $tdmsProductId,
            'action' => 'remove_favourite_request',
        ]);

        try {
            $removeResponse = FavouritesService::removeFavourite($request->user->uuid, $tdmsProductId);
            return $this->sendResponseFromService($removeResponse);
        } catch (ServiceException $e) {
            return $this->sendResponseFromService($e->toServiceResponse());
        }
    }
}
