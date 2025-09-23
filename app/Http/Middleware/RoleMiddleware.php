<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Traits\ResponseTrait;


class RoleMiddleware
{
    use ResponseTrait;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if ($request->route() && str_starts_with($request->route()->getName(), 'public.')) {
            return $next($request);
        }

        try {
            // Get authenticated user
            $user = JWTAuth::parseToken()->authenticate();

            // Check if user exists and has the required role
            if (!$user || !in_array($user->role, $roles)) {
                return $this->sendError('Access denied. Do not have permission.', [], 403);
            }
        } catch (\Exception $e) {
            return $this->sendError('Unauthorized. Please provide a valid token.', [], 401);
        }

        return $next($request);
    }

    
}
