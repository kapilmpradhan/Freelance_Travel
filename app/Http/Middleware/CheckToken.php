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
        $token = $request->header('token_access');
        $url = $request->header('request_url');

        dd($url, $token);
        $checkAuth = json_decode($this->quote->CallApi($url, $token), true);

        if (!@$checkAuth['bookingReference']) {
            return response()->json([
                'message' => __('auth.unauthenticated')
            ], 401);
        }

        return $next($request);
    }
}
