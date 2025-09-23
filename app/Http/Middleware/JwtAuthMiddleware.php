<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Auth\AuthenticationException;
use App\Traits\ResponseTrait;

class JwtAuthMiddleware
{
    use ResponseTrait;
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {

        // ✅ Now, only check token for protected routes
        try {
            if (!$request->hasHeader('Authorization')) {
                return $this->sendError('Authorization header is missing.', [], 401);
            }

            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return $this->sendError('User not found. Please log in again.', [], 401);
            }

            // Store the authenticated user in the request
            $request->merge(['auth_user' => $user]);

        } catch (TokenExpiredException $e) {
            return $this->sendError('Login has expired. Please log in again.', [], 401);
        } catch (TokenInvalidException $e) {
            return $this->sendError('Token is invalid.', [], 401);
        } catch (JWTException $e) {
            return $this->sendError('Token is missing or incorrect.', [], 401);
        } catch (AuthenticationException $e) {
            return $this->sendError('Unauthorized. Please provide a valid token.', [], 401);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while processing your request.', [], 500);
        }

        return $next($request);
    }
}