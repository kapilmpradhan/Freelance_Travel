<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Prometheus\PrometheusService;

class PrometheusMetricsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getPathInfo() === '/api/metrics') {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        if (config('prometheus.enabled') && config('prometheus.collect.http')) {
            app(PrometheusService::class)->recordHttpRequest(
                $request->getMethod(),
                $request->getPathInfo(),
                $response->getStatusCode(),
                $duration
            );
        }

        return $response;
    }
}
