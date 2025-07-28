<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckTokenIfAvailable
{
    protected $jwtAuthenticate;

    public function __construct(JwtAuthenticate $jwtAuthenticate)
    {
        $this->jwtAuthenticate = $jwtAuthenticate;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $authorizationHeader = $request->header('Authorization');
        if ($authorizationHeader) {
            return $this->jwtAuthenticate->handle($request, $next);
        } else {
            $request->merge(['user' => null]);
            return $next($request);
        }
    }
}
