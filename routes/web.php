<?php

use App\Http\Controllers\Client\BookingController;
use App\Jobs\UpdateFavoriteNightly;
use App\Models\Favourites;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['namespace' => 'App\Http\Controllers\Client', 'prefix' => ""], function () {
    Route::get('payment-success/{slug}', [BookingController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('payment-detail/{bookingReference}', [BookingController::class, 'paymentDetail'])->name('payment.detail');
    Route::get('/booking/{bookingReference}', [BookingController::class, 'bookingDetail'])->name('bookingDetail');
});

Route::get('/', function () {
    return redirect(env('APP_BASE_URL'));
});

Route::get('/test', function () {
    dispatch(new UpdateFavoriteNightly());
    dd(1);
});


Route::get('{slug}', function () {
    return redirect(env('APP_BASE_URL'));
});
