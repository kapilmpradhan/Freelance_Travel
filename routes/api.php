<?php

use App\Http\Controllers\Api\RedeemerController;
use Illuminate\Support\Facades\Route;
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
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\UserAgentController;
use App\Http\Controllers\Api\ProductController;

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
    Route::post('email/send-verification', [UserController::class, 'sendVerificationEmail']);
    Route::post('login/email/', [UserController::class, 'userLoginEmail']);
    Route::post('login/email/forgot-password/', [OtpController::class, 'sendOtp']);
    Route::post('email/otp/verify/', [OtpController::class, 'verifyOtp']);
    Route::post('email/reset-password/', [UserController::class, 'updatePassword']);
    Route::post('login/apple/', [AppleLoginController::class, 'userLoginApple']);
    Route::post('callback/apple/', [AppleLoginController::class, 'appleAuthCallback']);
    Route::post('login/google/', [GoogleLoginController::class, 'userLoginGoogle']);
    Route::post('token/access/', [UserController::class, 'accessTokenRegenerate']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api'], function () {
    Route::get('health-check', [HealthCheckController::class, 'healthCheck']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'products',], function () {
    Route::get('home', [ProductController::class, 'homeFeedProducts']);
    Route::get('home/v2', [ProductController::class, 'homeFeedProductsV2']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'user', 'middleware' => 'auth.jwt'], function () {
    Route::get('detail', [UserController::class, 'userDetail']);
    Route::put('detail', [UserController::class, 'updateProfile']);
    Route::put('nickname', [UserController::class, 'updateNickname']);
    Route::delete('delete', [UserController::class, 'deleteUserTemporarily']);
    Route::post('detail/change-password', [UserController::class, 'changePassword']);
    Route::post('/apple/new-email/add', [AppleLoginController::class, 'getRealEmailOTP']);
    Route::post('/apple/new-email/verify', [AppleLoginController::class, 'verifyRealEmailOTP']);
    Route::post('/fcm/token/add', [UserController::class, 'addFcmToken']);
    Route::delete('/fcm/token/{fcmToken}', [UserController::class, 'removeFcmToken']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'cart', 'middleware' => 'auth.jwt'], function () {
    Route::post('items', [CartItemController::class, 'addItemsToCart']);
    Route::post('items/v2', [CartItemController::class, 'addItemsToCartV2']);
    Route::get('items', [CartItemController::class, 'getItemsInCart']);
    Route::put('items/v2', [CartItemController::class, 'setBookingDataV2']);
    Route::put('items/{cartItemId}', [CartItemController::class, 'setBookingData']);
    Route::delete('items/{cartItemId}', [CartItemController::class, 'removeItemFromCart']);
    Route::delete('/remove/items', [CartItemController::class, 'removeItemsFromCart']);
    Route::put('customers', [CartItemController::class, 'setCustomers']);
    Route::get('customers', [CartItemController::class, 'getCustomers']);
    Route::get('redeemers', [RedeemerController::class, 'listRedeemers']);
    Route::post('redeemers', [RedeemerController::class, 'addRedeemer']);
    Route::delete('redeemers/{redeemerId}', [RedeemerController::class, 'removeRedeemer']);
    Route::post('add', [CartItemController::class, 'addItemToCart']);
    Route::get('list', [CartItemController::class, 'getCartItems']);
    Route::post('order', [CartItemController::class, 'submitOrder']);
    Route::post('order/v2', [CartItemController::class, 'submitOrderV2']);
    Route::post('order/v2/direct-purchase', [CartItemController::class, 'directPurchase']);
    Route::post('order/v2/direct-purchase/v2', [CartItemController::class, 'directPurchaseV2']);
    Route::get('bookings', [CartItemController::class, 'getBookings']);
    Route::delete('remove/{cartItemId}', [CartItemController::class, 'removeCartItem']);
    Route::post('items/to-quote', [CartItemController::class, 'addExistingCartItemsToQuote']);
    Route::post('items/to-quote/{quoteId}', [CartItemController::class, 'addExistingCartItemsToQuote']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'quotes', 'middleware' => 'auth.jwt'], function () {
    Route::post('new', [CartItemController::class, 'addItemsInNewQuote']);
    Route::post('new/v2', [CartItemController::class, 'addItemsInNewQuoteV2']);
    Route::get('{quoteId}', [CartItemController::class, 'getQuoteDetails']);
    Route::get('{quoteId}/items', [CartItemController::class, 'getItemsInQuote']);
    Route::put('{quoteId}/items/add', [CartItemController::class, 'addItemsInExistingQuote']);
    Route::put('{quoteId}/items/add/v2', [CartItemController::class, 'addItemsInExistingQuoteV2']);
    Route::delete('{quoteId}', [CartItemController::class, 'removeQuote']);
    Route::put('items/{cartItemId}', [CartItemController::class, 'setBookingData']);
    Route::delete('items/{cartItemId}', [CartItemController::class, 'removeItemFromCart']);
    Route::delete('remove/items', [CartItemController::class, 'removeItemsFromQuote']);
    Route::put('{quoteId}/customers', [CartItemController::class, 'setQuoteCustomers']);
    Route::get('{quoteId}/customers', [CartItemController::class, 'getQuoteCustomers']);
    Route::post('{quoteId}/order', [CartItemController::class, 'submitQuoteOrder']);
    Route::get('', [CartItemController::class, 'getQuotes']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'booking', 'middleware' => 'auth.jwt'], function () {
    Route::get('{bookingReference}/items', [CartItemController::class, 'getCartDetailsByBookingReference']);
    Route::get('{bookingReference}/detail', [CartItemController::class, 'getCustomerOrderDetail']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'cart'], function () {
    Route::put('order/complete', [CartItemController::class, 'completeOrder']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'product'], function () {
    Route::get('feed/categories', [CartItemController::class, 'categories']);
    Route::get('feed/schema', [ProductController::class, 'homeFeedSchema']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'agent', 'middleware' => 'auth.jwt'], function () {
    Route::get('integration', [UserAgentController::class, 'getUserAgent']);
    Route::get('integration/token', [UserAgentController::class, 'getUserAgentToken']);
    Route::post('integration', [UserAgentController::class, 'addUserAgent']);
    Route::put('integration', [UserAgentController::class, 'updateUserAgent']);
    Route::delete('integration', [UserAgentController::class, 'unlinkUserAgent']);
});

Route::group(['namespace' => 'App\Http\Controllers\Api', 'prefix' => 'agent'], function () {
    Route::get('default/token', [UserAgentController::class, 'getDefaultAgentToken']);
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
