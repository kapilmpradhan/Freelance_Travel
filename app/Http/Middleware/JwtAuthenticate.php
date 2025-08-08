<?php

namespace App\Http\Middleware;

use App\DTOs\UserAgentDTO;
use Closure;
use App\Services\JwtService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class JwtAuthenticate
{
    protected $jwtService;
    protected $responseService;

    public function __construct(JwtService $jwtService, ResponseService $responseService)
    {
        $this->jwtService = $jwtService;
        $this->responseService = $responseService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->responseService->sendError('Token not provided', [], 401);
        }

        $validated_data = $this->jwtService->validateAccessToken($token);

        if ($validated_data['error']) {
            return $this->responseService->sendError($validated_data['error'], [], 401);
        } elseif ($validated_data['tokenType'] != 'access') {
            return $this->responseService->sendError('Token is invalid', [], 401);
        }

        if (!$validated_data['user']) {
            return $this->responseService->sendError('User not found', [], 401);
        }

        // Set authenticated user in the application context
        Auth::login($validated_data['user']);

        $request->merge(['user' => $validated_data['user']]);
        App::instance('agentData', UserAgentDTO::getUserAgent($request->user, app('platform')));

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
