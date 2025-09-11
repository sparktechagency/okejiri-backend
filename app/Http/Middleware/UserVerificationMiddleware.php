<?php
namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserVerificationMiddleware
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
            return $this->responseError(null, 'Unauthorized.', 401);
        }

        if ($user->email_verified_at === null) {
            $metadata['redirect_verification'] = true;
            return $this->responseError(null, 'Your account is not verified. Please verify your email to access this resource.', 403, 'error', $metadata);
        }

        return $next($request);
    }
}
