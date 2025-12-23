<?php

namespace App\Http\Middleware;

use App\DTOs\UserAgentDTO;
use Closure;
use App\Services\JwtService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use App\Logging\Logger;

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
            Logger::debug('JWT authentication failed - no token', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'auth_no_token',
                'ip_address' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->responseService->sendError('Token not provided', [], 401);
        }

        $validated_data = $this->jwtService->validateAccessToken($token);

        if ($validated_data['error']) {
            Logger::debug('JWT authentication failed - token error', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'auth_token_error',
                'error' => $validated_data['error'],
                'ip_address' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->responseService->sendError($validated_data['error'], [], 401);
        } elseif ($validated_data['tokenType'] != 'access') {
            Logger::debug('JWT authentication failed - wrong token type', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'auth_wrong_token_type',
                'ip_address' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->responseService->sendError('Token is invalid', [], 401);
        }

        if (!$validated_data['user']) {
            Logger::debug('JWT authentication failed - user not found', [
                'log_file' => config('logging.log_files.user_auth'),
                'action' => 'auth_user_not_found',
                'ip_address' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->responseService->sendError('User not found', [], 401);
        }

        // Set authenticated user in the application context
        Auth::login($validated_data['user']);

        $request->merge(['user' => $validated_data['user']]);
        $agentType = UserAgentDTO::getUserAgent($request->user, app('platform'));

        App::instance('agentType', $agentType);

        Logger::debug('JWT authentication successful', [
            'log_file' => config('logging.log_files.user_auth'),
            'user_id' => $validated_data['user']->uuid,
            'user_email' => $validated_data['user']->email,
            'action' => 'auth_success',
            'ip_address' => $request->ip(),
            'path' => $request->path(),
        ]);

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
