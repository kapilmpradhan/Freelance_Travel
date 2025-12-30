<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CacheController;

/*
|--------------------------------------------------------------------------
| Cache Routes
|--------------------------------------------------------------------------
|
| Routes for caching product and user resources.
|
*/

Route::middleware('auth.ifToken')->group(function () {
    Route::get('product/{tdmsProductId}', [CacheController::class, 'cacheLatestProductDetails']);
    Route::get('product/{tdmsProductId}/price/{ppdid}/details', [CacheController::class, 'cacheProductPriceDetails']);
    Route::get('user/resources', [CacheController::class, 'cacheUserResources']);
});
