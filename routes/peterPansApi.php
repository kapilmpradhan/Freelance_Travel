<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppMetaDataController;
use App\Http\Controllers\Api\AppleLoginController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\FavouritesController;
use App\Http\Controllers\Api\GoogleLoginController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RedeemerController;
use App\Http\Controllers\Api\SharedPaymentController;
use App\Http\Controllers\Api\UserAgentController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| PeterPans API Routes
|--------------------------------------------------------------------------
|
| API routes for the PeterPans platform variant.
|
*/

// Health Check
Route::get('health-check', [HealthCheckController::class, 'healthCheck']);

// Public User Routes
Route::prefix('user')->group(function () {
    Route::post('signup/email', [UserController::class, 'userSignupEmail']);
    Route::post('login/email', [UserController::class, 'userLoginEmail']);
    Route::post('login/email/forgot-password', [OtpController::class, 'sendOtp']);
    Route::post('email/otp/verify', [OtpController::class, 'verifyOtp']);
    Route::post('email/reset-password', [UserController::class, 'updatePassword']);
    Route::post('login/apple', [AppleLoginController::class, 'userLoginApple']);
    Route::post('callback/apple', [AppleLoginController::class, 'appleAuthCallback']);
    Route::post('login/google', [GoogleLoginController::class, 'userLoginGoogle']);
    Route::post('token/access', [UserController::class, 'accessTokenRegenerate']);
});

// Authenticated User Routes
Route::prefix('user')->middleware('auth.jwt')->group(function () {
    Route::get('detail', [UserController::class, 'userDetail']);
    Route::put('detail', [UserController::class, 'updateProfile']);
    Route::post('email/send-verification', [UserController::class, 'sendVerificationEmail']);
    Route::put('nickname', [UserController::class, 'updateNickname']);
    Route::delete('delete', [UserController::class, 'deleteUserTemporarily']);
    Route::post('detail/change-password', [UserController::class, 'changePassword']);
    Route::post('apple/new-email/add', [AppleLoginController::class, 'getRealEmailOTP']);
    Route::post('apple/new-email/verify', [AppleLoginController::class, 'verifyRealEmailOTP']);
    Route::post('points/show-hide', [UserController::class, 'showHidePoints']);
    Route::post('fcm/token/add', [UserController::class, 'addFcmToken']);
    Route::delete('fcm/token/{fcmToken}', [UserController::class, 'removeFcmToken']);
    Route::get('metaData', [UserController::class, 'userMetaData']);
});

// Public Product Routes
Route::prefix('products')->group(function () {
    Route::get('home/v2', [ProductController::class, 'homeFeedProductsV2'])->middleware('auth.ifToken');
    Route::get('home/locations', [ProductController::class, 'homeTabLocations']);
});

Route::prefix('product')->group(function () {
    Route::get('feed/schema', [ProductController::class, 'homeFeedSchema']);
});

// App Metadata
Route::prefix('app')->group(function () {
    Route::get('minSupportVersion', [AppMetaDataController::class, 'getMobileMinSupportedVersion']);
});

// Admin Routes
Route::prefix('admin')->middleware('admin')->group(function () {
    Route::post('discount/add', [DiscountController::class, 'addNewDiscount']);
    Route::get('discount/all', [DiscountController::class, 'getAllDiscounts']);
    Route::put('discount/{discountId}/update', [DiscountController::class, 'updateDiscount']);
    Route::delete('discount/{discountId}/delete', [DiscountController::class, 'deleteDiscount']);
    Route::post('send/notification/topic', [AdminController::class, 'sendNotificationToTopic']);
    Route::post('mobile/minSupportVersion', [AppMetaDataController::class, 'setMobileMinSupportedVersion']);
});

// Discount Routes (auth optional)
Route::prefix('discount')->middleware('auth.ifToken')->group(function () {
    Route::get('active', [DiscountController::class, 'getDiscount']);
});

// Cart Routes (auth optional)
Route::prefix('cart')->middleware('auth.ifToken')->group(function () {
    Route::post('items/v2', [CartItemController::class, 'addItemsToCartV2']);
    Route::get('items', [CartItemController::class, 'getItemsInCart']);
    Route::put('items', [CartItemController::class, 'setBookingData']);
    Route::delete('remove/items', [CartItemController::class, 'removeItemsFromCart']);
    Route::get('redeemers', [RedeemerController::class, 'listRedeemers']);
    Route::post('redeemers', [RedeemerController::class, 'addRedeemer']);
    Route::delete('redeemers/{redeemerId}', [RedeemerController::class, 'removeRedeemer']);
    Route::post('discount/v2', [CartItemController::class, 'getDiscountPercentageV2']);
});

// Cart Routes (authenticated)
Route::prefix('cart')->middleware('auth.jwt')->group(function () {
    Route::post('order', [CartItemController::class, 'submitOrder']);
    Route::post('order/v2/direct-purchase/v2', [CartItemController::class, 'directPurchaseV2']);
    Route::get('bookings', [CartItemController::class, 'getBookings']);
    Route::delete('remove/{cartItemId}', [CartItemController::class, 'removeCartItem']);
    Route::post('items/to-quote', [CartItemController::class, 'addExistingCartItemsToQuote']);
    Route::post('items/to-quote/{quoteId}', [CartItemController::class, 'addExistingCartItemsToQuote']);
    Route::put('items/update', [CartItemController::class, 'updateWithLatestDetails']);
    Route::put('convert/session/{sessionId}', [CartItemController::class, 'convertSessionItemsToCartItems']);
});

// Cart Public Routes
Route::prefix('cart')->group(function () {
    Route::put('order/complete', [CartItemController::class, 'completeOrder']);
});

// Quotes Routes
Route::prefix('quotes')->middleware('auth.jwt')->group(function () {
    Route::get('', [CartItemController::class, 'getQuotes']);
    Route::post('new/v2', [CartItemController::class, 'addItemsInNewQuoteV2']);
    Route::get('all', [CartItemController::class, 'getAllQuotes'])->middleware('pointsAndCommissionAgentOnly');
    Route::get('my', [CartItemController::class, 'getMyQuotes'])->middleware('pointsAndCommissionAgentOnly');
    Route::get('share/to/me', [CartItemController::class, 'getQuotesSharedToMe']);
    Route::post('share/{quoteId}', [CartItemController::class, 'shareQuote']);
    Route::get('share/{quoteId}/users', [CartItemController::class, 'getQuoteSharedUsers']);
    Route::post('share/accept/{quoteShareId}', [CartItemController::class, 'acceptQuoteInvite']);
    Route::post('share/reject/{quoteShareId}', [CartItemController::class, 'rejectQuoteInvite']);
    Route::delete('items/{cartItemId}', [CartItemController::class, 'removeItemFromCart']);
    Route::delete('remove/items', [CartItemController::class, 'removeItemsFromQuote']);

    // Quote-specific routes
    Route::get('{quoteId}', [CartItemController::class, 'getQuoteDetails']);
    Route::delete('{quoteId}', [CartItemController::class, 'removeQuote']);
    Route::get('{quoteId}/items', [CartItemController::class, 'getItemsInQuote']);
    Route::put('{quoteId}/items/add/v2', [CartItemController::class, 'addItemsInExistingQuoteV2']);
    Route::put('{quoteId}/customers', [CartItemController::class, 'setQuoteCustomers']);
    Route::get('{quoteId}/customers', [CartItemController::class, 'getQuoteCustomers']);
    Route::post('{quoteId}/order', [CartItemController::class, 'submitQuoteOrder']);
    Route::post('{quoteId}/payment/share', [SharedPaymentController::class, 'sharePaymentLink']);
    Route::get('{quoteId}/list/payment/shared', [SharedPaymentController::class, 'sharedPaymentSentList']);
    Route::post('{quoteId}/add/payment/receivers', [SharedPaymentController::class, 'addPaymentLinkReceiver']);
    Route::get('{quoteId}/list/payment/receivers', [SharedPaymentController::class, 'listPaymentReceivers']);
    Route::post('{quoteId}/resend/{sharePaymentId}', [SharedPaymentController::class, 'resendPaymentLink']);
});

// Booking Routes
Route::prefix('booking')->middleware('auth.jwt')->group(function () {
    Route::get('all', [CartItemController::class, 'getAllUserOrders'])->middleware('pointsAndCommissionAgentOnly');
    Route::get('{bookingReference}/items', [CartItemController::class, 'getCartDetailsByBookingReference']);
    Route::get('{bookingReference}/detail', [CartItemController::class, 'getCustomerOrderDetail']);
});

// Agent Routes (public)
Route::prefix('agent')->group(function () {
    Route::get('default/token', [UserAgentController::class, 'getDefaultAgentToken']);
});

// Agent Routes (authenticated)
Route::prefix('agent')->middleware('auth.jwt')->group(function () {
    Route::get('integration', [UserAgentController::class, 'getUserAgent']);
    Route::post('integration', [UserAgentController::class, 'addUserAgent']);
    Route::put('integration', [UserAgentController::class, 'updateUserAgent']);
    Route::delete('integration', [UserAgentController::class, 'unlinkUserAgent']);
    Route::get('integration/token', [UserAgentController::class, 'getUserAgentToken']);
    Route::get('integration/commissionReport', [UserAgentController::class, 'getCommissionReport']);
    Route::post('integration/points', [UserAgentController::class, 'getAndUpdateUserAgentPoints']);
    Route::post('integration/upgradeToAgent', [UserAgentController::class, 'upgradeToAgent']);
});

// Favourites Routes
Route::prefix('favourite')->middleware('auth.jwt')->group(function () {
    Route::get('products/all', [FavouritesController::class, 'getFavouriteProducts']);
    Route::post('products/add/{tdmsProductId}', [FavouritesController::class, 'addFavouriteProduct']);
    Route::delete('products/remove/{tdmsProductId}', [FavouritesController::class, 'removeFavouriteProduct']);
});
