<?php

namespace App\Http\Middleware;

use App\Logging\Logger;
use Closure;

class LogRouteMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $timeTaken = round((microtime(true) - $startTime) * 1000, 2);

        Logger::info("[{$request->getMethod()}]", [
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
            'time_ms' => $timeTaken,
        ]);

        return $response;
    }
}
