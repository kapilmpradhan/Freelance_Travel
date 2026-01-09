<?php

namespace App\Http\Middleware;

use App\Services\ResponseService;
use App\Services\TdmsService;
use App\Services\UserAgentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TdmsWebhookMiddleware
{
    protected $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->responseService->sendError('Bearer token not provided', [], 401);
        }

        $checkBranch = TdmsService::getBookingRefrence($token, false);
        if (is_null($checkBranch)) {
            return $this->responseService->sendError('Invalid bearer token', [], 401);
        }

        $branch = substr($checkBranch, 0, 3);
        if ($branch !== 'FTX') {
            return $this->responseService->sendError('Invalid bearer token for webhook', [], 401);
        }

        return $next($request);
    }

    private function getTokenFromRequest(Request $request)
    {
        $authorizationHeader = $request->header('Authorization');

        if ($authorizationHeader && preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
