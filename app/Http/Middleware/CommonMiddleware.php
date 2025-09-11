<?php
namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CommonMiddleware
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
            $user = Auth::user();

            $allowedRoles = ['USER', 'ADMIN'];

            if (! $user) {
                return $this->responseError(null, 'Unauthorized.', 401);
            }
            if (! in_array($user->role, $allowedRoles)) {
                return $this->responseError(null, 'You (' . strtolower($user->role) . ') are not allowed. Allowed roles are: ' . implode(', ', array_map('strtolower', $allowedRoles)), 403);
            }

            return $next($request);

        } catch (Exception $exception) {
            return $this->responseError($exception->getMessage(), 'Unauthorized.', 401);
        }

    }

}
