<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Notifications\ResetPasswordNotification;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\URL;
use App\Models\Tenant;

class AuthController extends Controller
{
    use ResponseTrait;

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            JWTAuth::factory()->setTTL(2880); 

            if (!$accessToken = JWTAuth::attempt($request->only('email', 'password'))) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            $user = Auth::user();


            JWTAuth::factory()->setTTL(20160);
            $refreshToken = JWTAuth::fromUser($user);


            return $this->sendResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60, 
                'user' => auth('api')->user(),
            ], 'Login successful.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Error during login: ' . $e->getMessage(), [], 500);
        }
    }

    
    // Logout method
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            
            return $this->sendResponse([], 'Logged out successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to logout'. $e->getMessage(), []);
        }
    }


    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ], [
                'email.exists' => 'User not found with this email address.',
            ]);

            $status = Password::broker()->sendResetLink(
                $request->only('email'),
                function ($user, $token) {
                    $user->notify(new ResetPasswordNotification($token));
                }
            );

            if ($status === Password::RESET_LINK_SENT) {
                return $this->sendResponse([], 'Password reset link sent to your email.');
            }

            return $this->sendError('Failed to send reset link. Please try again later.', []);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the forgot password process.' .$e->getMessage(), []);
        }
    }


    public function resetPassword(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|string|min:6|confirmed',
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->password = Hash::make($password);
                    $user->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->sendResponse([], 'Password has been reset successfully.');
            }

            return $this->sendError('Failed to reset password.', []);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), []);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the password reset process.' .$e->getMessage(), []);
        }
    }


    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($request->current_password, Auth::user()->password)) {
                return $this->sendError('Current password is incorrect.', []);
            }

            $user = Auth::user();
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->sendResponse([], 'Password updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('An error occurred while updating the password.' .$e->getMessage(), []);
        }

    }

    public function refreshToken(Request $request)
    {
        try {
            $authorizationHeader = $request->header('Authorization');

            if (!$authorizationHeader || !preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
                return $this->sendError('Refresh token is required in the Authorization header', [], 401);
            }

            $refreshToken = $matches[1];

            try {
                $newToken = JWTAuth::setToken($refreshToken)->refresh();
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                return $this->sendError('Refresh token has expired. Please log in again.', [], 401);
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                return $this->sendError('Invalid refresh token.', [], 401);
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return $this->sendError('Refresh token error: ' . $e->getMessage(), [], 401);
            }

            return $this->sendResponse([
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ], 'Token refreshed successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error refreshing token.', [], 500);
        }
    }


    

    
    
}
