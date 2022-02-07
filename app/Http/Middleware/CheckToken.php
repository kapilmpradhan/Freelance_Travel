<?php

namespace App\Http\Middleware;

use App\Models\Quote;
use Closure;
use Illuminate\Http\Request;

class CheckToken
{
    protected $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->token_access;
        $url = $request->request_url;
        if ($request->hasHeader('FT_token')) {
            $token = $request->header('FT_token');
        }
        if ($request->hasHeader('FT_URL')) {
            $url = $request->header('FT_URL');
        }

        $checkAuth = json_decode($this->quote->CallApi($url, $token), true);

        if (!@$checkAuth['bookingReference']) {
            dd($url, $token);
            return response()->json([
                'message' => __('auth.unauthenticated')
            ], 401);
        }

        return $next($request);
    }
}
