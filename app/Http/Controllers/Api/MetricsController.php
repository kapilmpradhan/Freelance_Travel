<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Logging\Logger;
use App\Services\Prometheus\PrometheusService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __invoke(PrometheusService $prometheus): Response
    {
        // Check if native PHP Redis extension is available
        if (!extension_loaded('redis')) {
            Logger::info('PHP Redis extension not available, using InMemory storage for Prometheus metrics');
            $errorMessage = "# HELP prometheus_storage_error PHP Redis extension not installed\n";
            $errorMessage .= "# TYPE prometheus_storage_error gauge\n";
            $errorMessage .= "prometheus_storage_error{reason=\"redis_extension_missing\"} 1\n";

            return response($errorMessage, 400, [
                'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            ]);
        }

        return response($prometheus->render(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
