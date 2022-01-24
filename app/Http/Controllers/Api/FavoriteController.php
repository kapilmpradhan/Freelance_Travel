<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BaseResource;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use Illuminate\Http\Request;
use Validator;

class FavoriteController extends BaseController
{
    public function addFavorite(Request $request, Favorite $favorite)
    {
        $validate = Validator::make($request->all(), $favorite->rule($request));
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors(), 422);
        }
        $create = $favorite->addFavorite($request->email, $request->product_id, $request->json);
        return $this->sendResponse(new FavoriteResource($create), __('successfully'), 200);
    }

    public function getFavorite($email, Favorite $favorite)
    {
        $favorites = $favorite->getFavoriteByEmail($email);
        return FavoriteResource::collection($favorites);
    }

    public function removeFavorite($email, Request $request, Favorite $favorite)
    {
        $favorite->removeFavorite($email, $request->product_id);

        return $this->sendResponse("ok", __('successfully'), 200);
    }

    public function saveFavorite(Request $request, Favorite $favorite)
    {
        $validate = Validator::make($request->all(), $favorite->rule($request));
        if ($validate->fails()) {
            return $this->sendError('Validation Error.', $validate->errors(), 422);
        }
        $create = $favorite->storeFavorite($request);
        return $this->sendResponse(new BaseResource($create), __('successfully'), 200);
    }

    public function detailFavorite($email, Favorite $favorite)
    {
        $favorite = $favorite->getDetailFavorite($email);

        if ($favorite) {
            return $this->sendResponse(new BaseResource($favorite), __('successfully'), 200);
        }
        return $this->sendResponse(null, __('fail'), 200);
    }
}
