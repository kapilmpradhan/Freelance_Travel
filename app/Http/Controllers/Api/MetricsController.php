<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Prometheus\PrometheusService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(PrometheusService $prometheus): Response
    {
        return response($prometheus->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
