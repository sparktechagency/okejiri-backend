<?php
namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    use ApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user        = Auth::user();
            $allowedRole = 'ADMIN';

            if (! $user) {
                return $this->responseError(null, 'Unauthorized.', 401);
            }

            if ($user->role !== $allowedRole) {
                return $this->responseError(null, 'Permission denied for role (' . strtolower($user->role) . '). Only ' . strtolower($allowedRole) . 's are allowed.', 403);
            }

            return $next($request);

        } catch (Exception $exception) {
            return $this->responseError($exception->getMessage(), 'Unauthorized.', 401);
        }
    }
}
