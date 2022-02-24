<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Favourites;

class FavouritesController extends BaseController
{
    public function addFavorite(Request $request, Favourites $favourites)
    {
        $create = $favourites->addFavorite($request->email, $request->productId, $request->all());
        return $this->sendResponse($create, __('successfully'), 200);
    }


    public function getFavourites($email, Favourites $favourites)
    {
        $favourites = $favourites->getFavoriteByEmail($email);
        return $this->sendResponse($favourites, __('successfully'), 200);
    }

    public function deleteFavorite(Request $request, Favourites $favourites)
    {
        $favourites->removeFavorite($request->email, $request->productId);
        return $this->sendResponse([], __('successfully'), 200);
    }
}
