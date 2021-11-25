<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;

class CheckTokenSuccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = AccessToken::first()->pluck('token');
        $header = $request->header('token_access');
        if($token[0] != $header){
            return response()->json([
                'message'=>__('auth.unauthenticated')
            ], 401);
        }
        return $next($request);
    }
}
