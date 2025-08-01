<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppleLoginController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\FavouritesController;
use App\Http\Controllers\Api\GoogleLoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RedeemerController;
use App\Http\Controllers\Api\UserAgentController;
use App\Http\Controllers\Api\UserController;

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

Route::group(['namespace' => 'App\Http\Controllers\Api'], function () {
    Route::get('health-check', [HealthCheckController::class, 'healthCheck']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'user',], function () {
    Route::post('signup/email/', [UserController::class, 'userSignupEmail']);
    Route::post('login/email/', [UserController::class, 'userLoginEmail']);
    Route::post('login/email/forgot-password/', [OtpController::class, 'sendOtp']);
    Route::post('email/otp/verify/', [OtpController::class, 'verifyOtp']);
    Route::post('email/reset-password/', [UserController::class, 'updatePassword']);
    Route::post('login/apple/', [AppleLoginController::class, 'userLoginApple']);
    Route::post('callback/apple/', [AppleLoginController::class, 'appleAuthCallback']);
    Route::post('login/google/', [GoogleLoginController::class, 'userLoginGoogle']);
    Route::post('token/access/', [UserController::class, 'accessTokenRegenerate']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'user', 'middleware' => 'auth.jwt'], function () {
    Route::get('detail', [UserController::class, 'userDetail']);
    Route::put('detail', [UserController::class, 'updateProfile']);
    Route::post('email/send-verification', [UserController::class, 'sendVerificationEmail']);
    Route::put('nickname', [UserController::class, 'updateNickname']);
    Route::delete('delete', [UserController::class, 'deleteUserTemporarily']);
    Route::post('detail/change-password', [UserController::class, 'changePassword']);
    Route::post('/apple/new-email/add', [AppleLoginController::class, 'getRealEmailOTP']);
    Route::post('/apple/new-email/verify', [AppleLoginController::class, 'verifyRealEmailOTP']);
    Route::post('/fcm/token/add', [UserController::class, 'addFcmToken']);
    Route::delete('/fcm/token/{fcmToken}', [UserController::class, 'removeFcmToken']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'agent'], function () {
    Route::get('default/token', [UserAgentController::class, 'getDefaultAgentToken']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'product'], function () {
    Route::get('feed/schema', [ProductController::class, 'homeFeedSchema']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'products',], function () {
    Route::get('home/v2', [ProductController::class, 'homeFeedProductsV2']);
    Route::get('home/locations', [ProductController::class, 'homeTabLocations']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'cart', 'middleware' => 'auth.jwt'], function () {
    Route::post('items/v2', [CartItemController::class, 'addItemsToCartV2']);
    Route::get('items', [CartItemController::class, 'getItemsInCart']);
    Route::put('items/v2', [CartItemController::class, 'setBookingDataV2']);
    Route::delete('/remove/items', [CartItemController::class, 'removeItemsFromCart']);
    Route::get('redeemers', [RedeemerController::class, 'listRedeemers']);
    Route::post('redeemers', [RedeemerController::class, 'addRedeemer']);
    Route::delete('redeemers/{redeemerId}', [RedeemerController::class, 'removeRedeemer']);
    Route::post('order', [CartItemController::class, 'submitOrder']);
    Route::post('order/v2/direct-purchase/v2', [CartItemController::class, 'directPurchaseV2']);
    Route::get('bookings', [CartItemController::class, 'getBookings']);
    Route::delete('remove/{cartItemId}', [CartItemController::class, 'removeCartItem']);
    Route::post('items/to-quote', [CartItemController::class, 'addExistingCartItemsToQuote']);
    Route::post('items/to-quote/{quoteId}', [CartItemController::class, 'addExistingCartItemsToQuote']);
    Route::post('discount/v2', [CartItemController::class, 'getDiscountPercentageV2']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'quotes', 'middleware' => 'auth.jwt'], function () {
    Route::post('new/v2', [CartItemController::class, 'addItemsInNewQuoteV2']);
    Route::get('{quoteId}', [CartItemController::class, 'getQuoteDetails']);
    Route::get('{quoteId}/items', [CartItemController::class, 'getItemsInQuote']);
    Route::put('{quoteId}/items/add/v2', [CartItemController::class, 'addItemsInExistingQuoteV2']);
    Route::delete('{quoteId}', [CartItemController::class, 'removeQuote']);
    Route::delete('items/{cartItemId}', [CartItemController::class, 'removeItemFromCart']);
    Route::delete('remove/items', [CartItemController::class, 'removeItemsFromQuote']);
    Route::put('{quoteId}/customers', [CartItemController::class, 'setQuoteCustomers']);
    Route::get('{quoteId}/customers', [CartItemController::class, 'getQuoteCustomers']);
    Route::post('{quoteId}/order', [CartItemController::class, 'submitQuoteOrder']);
    Route::get('', [CartItemController::class, 'getQuotes']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'cart'], function () {
    Route::put('order/complete', [CartItemController::class, 'completeOrder']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'favourite', 'middleware' => 'auth.jwt'], function () {
    Route::get('products/all', [FavouritesController::class, 'getFavouriteProducts']);
    Route::post('products/add/{tdmsProductId}', [FavouritesController::class, 'addFavouriteProduct']);
    Route::delete('products/remove/{tdmsProductId}', [FavouritesController::class, 'removeFavouriteProduct']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'discount', 'middleware' => 'auth.ifToken'], function () {
    Route::get('/active', [DiscountController::class, 'getDiscount']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'admin', 'middleware' => 'admin'], function () {
    Route::post('/discount/add', [DiscountController::class, 'addNewDiscount']);
    Route::get('/discount/all', [DiscountController::class, 'getAllDiscounts']);
    Route::put('/discount/{discountId}/update', [DiscountController::class, 'updateDiscount']);
    Route::delete('/discount/{discountId}/delete', [DiscountController::class, 'deleteDiscount']);
    Route::post('/send/notification/topic', [AdminController::class, 'sendNotificationToTopic']);
});