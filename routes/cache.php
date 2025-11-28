<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CacheController;


Route::group(['namespace' => 'App\Http\Controllers\Api', 'middleware' => 'auth.jwt'], function () {
    Route::get('product/{tdmsProductId}', [CacheController::class, 'cacheLatestProductDetails']);
    Route::get('product/{tdmsProductId}/price/{ppdid}/details', [CacheController::class, 'cacheProductPriceDetails']);
    Route::get('user/resources', [CacheController::class, 'cacheUserResources']);
});