<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\BookingController;
use App\Http\Controllers\Api\GoogleLoginController;
use App\Http\Controllers\Admin\AdminAuthController;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;
use Laravel\Telescope\Telescope;
use Illuminate\Support\Facades\Auth;

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

Telescope::auth(function ($request) {
    return Auth::check() && Auth::user()->is_ops;
});


Route::group(['namespace' => 'App\Http\Controllers\Client', 'prefix' => ""], function () {
    Route::get('payment-success/{slug}', [BookingController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('payment-detail/{bookingReference}', [BookingController::class, 'paymentDetail'])->name('payment.detail');
    Route::get('/booking/{bookingReference}', [BookingController::class, 'bookingDetail'])->name('bookingDetail');
});


Route::get('{slug}', function () {
    return redirect(config('constants.base_url'));
});


Route::group(['middleware' => ['web']], function () {
    Route::get('auth/google/callback/', [GoogleLoginController::class, 'handleGoogleCallback']);
});

Route::group(['prefix' => 'admin'], function () {
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login'])->name('login.submit');
    Route::get('logout', [AdminAuthController::class, 'logout'])->name('logout');
});

Route::middleware('auth')->get('admin/logs', [LogViewerController::class, 'index'])->name('logs');