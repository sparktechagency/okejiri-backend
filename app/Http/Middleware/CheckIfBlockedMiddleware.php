<?php
namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckIfBlockedMiddleware
{
    use ApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (! $user) {
            return $this->responseError(null, 'Unauthorized. Please login.', 401);
        }
        if ($user->is_blocked) {
            return $this->responseError(null, 'Your account has been blocked. Please contact support.', 403);
        }
        return $next($request);
    }
}
