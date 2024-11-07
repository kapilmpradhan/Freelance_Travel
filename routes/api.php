<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\BaseController;
use App\Http\Controllers\Api\BCRController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FavouritesController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\QuotetController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GoogleLoginController;
use App\Http\Controllers\Api\AppleLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'user',], function () {
    Route::post('signup/email/', [UserController::class, 'userSignupEmail']);
    Route::post('login/email/', [UserController::class, 'userLoginEmail']);
    Route::post('login/email/forgot-password/', [UserController::class, 'forgotPasswordSendOtp']);
    Route::post('login/apple/', [AppleLoginController::class, 'userLoginApple']);
    Route::post('callback/apple/', [AppleLoginController::class, 'appleAuthCallback']);
    Route::post('login/google/', [GoogleLoginController::class, 'userLoginGoogle']);
    Route::post('token/access/', [UserController::class, 'accessTokenRegenerate']);
});


Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => '', 'middleware' => 'checkToken'], function () {
    Route::post('connect-payment', [BaseController::class, 'connectPayment']);
    Route::post('share-booking', [BaseController::class, 'shareBooking']);
    Route::post('resend-voucher/{orderId}', [QuotetController::class, 'resendVoucher']);
    Route::post('save-account', [AccountController::class, 'saveAccount']);
    Route::get('detail-account/{email}', [AccountController::class, 'detailAccount']);
    Route::post('save-quote', [QuotetController::class, 'saveQuote']);
    Route::get('detail-quote/{email}', [QuotetController::class, 'detailQuote']);
    Route::post('favorites/add', [FavoriteController::class, 'addFavorite']);
    Route::post('favourites/add', [FavouritesController::class, 'addFavorite']);
    Route::get('favorites/{email}', [FavoriteController::class, 'getFavorite']);
    Route::get('favourites/{email}', [FavouritesController::class, 'getFavourites']);
    Route::post('favourites/delete', [FavouritesController::class, 'deleteFavorite']);
    Route::delete('favorites/{email}', [FavoriteController::class, 'removeFavorite']);
    Route::post('save-favorite', [FavoriteController::class, 'saveFavorite']);
    Route::get('detail-favorite/{email}', [FavoriteController::class, 'detailFavorite']);
    Route::get('cancel-requests', [BCRController::class, 'getAll']);
    Route::post('cancel-requests', [BCRController::class, 'CancelRequest']);
    Route::get('cancel-requests/{bookingReference}', [BCRController::class, 'getDetail']);
});

Route::post("email-log", [LogController::class, "log"]);

Route::post('send-mail', [BaseController::class, 'sendMail']);

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'user', 'middleware' => 'auth.jwt'], function () {
    Route::get('detail', [UserController::class, 'userDetail']);
});
