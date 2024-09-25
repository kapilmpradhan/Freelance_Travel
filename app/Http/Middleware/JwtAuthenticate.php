<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], Response::HTTP_UNAUTHORIZED);
        }

        $isValid = $this->jwtService->validateToken($token);

        if (!$isValid) {
            return response()->json(['error' => 'Invalid or expired token'], Response::HTTP_UNAUTHORIZED);
        }

        // Optionally, set user context (e.g., $request->user = $isValid)
        $request->user = $isValid; 

        return $next($request);
    }

    /**
     * Extract token from request header
     */
    private function getTokenFromRequest(Request $request)
    {
        $authorizationHeader = $request->header('Authorization');

        if ($authorizationHeader && preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
