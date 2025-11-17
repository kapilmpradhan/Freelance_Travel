<?php

namespace App\Http\Middleware;

use App\DTOs\UserAgentDTO;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

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
            $agentType = UserAgentDTO::getUserAgent(null, app('platform'));
            App::instance('agentType', $agentType);
            $request->merge(['user' => null]);
            return $next($request);
        }
    }
}
