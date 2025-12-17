<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MetricsController;

/*
|--------------------------------------------------------------------------
| Prometheus Metrics Routes
|--------------------------------------------------------------------------
|
| This route exposes application metrics in Prometheus format.
| Access via /metrics endpoint (internal network access only).
|
*/

Route::get('', MetricsController::class);
