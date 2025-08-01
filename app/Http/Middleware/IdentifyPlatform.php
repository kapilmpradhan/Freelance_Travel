<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\AgentBranchCode;
use Illuminate\Support\Facades\App;

class IdentifyPlatform
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $url = $request->url();
        if (str_contains($url, 'api/peterpans')) {
            App::instance('platform', AgentBranchCode::PETERPANS);
            $request->merge(['agentBranchCode' => AgentBranchCode::PETERPANS]);
        } else {
            App::instance('platform', AgentBranchCode::DEFAULT);
            $request->merge(['agentBranchCode' => AgentBranchCode::DEFAULT]);
        }
        return $next($request);
    }
}
