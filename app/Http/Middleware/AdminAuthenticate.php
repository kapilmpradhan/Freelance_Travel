<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ResponseService;
use Illuminate\Support\Facades\Hash;

class AdminAuthenticate
{
    protected $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Retrieve Authorization header
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return $this->responseService->sendError('Authorization header not provided', [], 401);
        }

        // Decode the Basic auth credentials
        $credentials = base64_decode(substr($authHeader, 6));
        [$email, $password] = explode(':', $credentials, 2);

        $user = User::where('email', $email)
            // ->where('password', Hash::make($password))
            ->where('is_ops', true)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return $this->responseService->sendError('Invalid credentials', [], 401);
        }

        // Attempt to authenticate the user
        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $next($request);
    }
}
