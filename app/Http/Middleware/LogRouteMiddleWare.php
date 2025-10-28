<?php

namespace App\Http\Middleware;

use App\Logging\Logger;
use Closure;

class LogRouteMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        Logger::info("[{$request->getMethod()}]", [
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
