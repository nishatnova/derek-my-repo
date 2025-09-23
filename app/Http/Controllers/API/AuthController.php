<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Password;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\PasswordResetCode;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Notifications\SendPasswordResetCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
            ], [
                'email.exists' => 'User not found with this email address.',
            ]);

            $emailCacheKey = "forgot_password:{$validated['email']}";
            $emailAttempts = Cache::get($emailCacheKey, 0);
            
            if ($emailAttempts >= 30) {
                return $this->sendError('Too many password reset requests. Please try again in 15 minutes.', [], 429);
            }

            $ipCacheKey = "forgot_password_ip:" . $request->ip();
            $ipAttempts = Cache::get($ipCacheKey, 0);
            
            if ($ipAttempts >= 40) {
                return $this->sendError('Too many requests from this IP. Please try again later.', [], 429);
            }

            $result = DB::transaction(function () use ($validated, $emailCacheKey, $ipCacheKey) {
                PasswordResetCode::where('email', $validated['email'])->delete();

                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                PasswordResetCode::create([
                    'email' => $validated['email'],
                    'code' => $code,
                    'expires_at' => now()->addMinutes(10),
                ]);

                Cache::put($emailCacheKey, Cache::get($emailCacheKey, 0) + 1, now()->addMinutes(15));
                Cache::put($ipCacheKey, Cache::get($ipCacheKey, 0) + 1, now()->addHour());

                return $code;
            });

            $user = new \stdClass();
            $user->email = $validated['email'];
            Notification::route('mail', $validated['email'])
                ->notify((new SendPasswordResetCode($result))->onQueue('emails'));

            return $this->sendResponse([], 'Verification code sent to your email.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the forgot password process.' . $e->getMessage(), []);
        }
    }

    public function verifyCode(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
                'email' => 'required|email|exists:users,email',
            ], [
                'email.exists' => 'User not found with this email address.',
            ]);

            $cacheKey = "verify_attempts:{$validated['email']}";
            $attempts = Cache::get($cacheKey, 0);
            
            if ($attempts >= 30) {
                return $this->sendError('Too many verification attempts. Please try again in 15 minutes.', [], 429);
            }

            $result = DB::transaction(function () use ($validated, $cacheKey) {
                $resetCode = PasswordResetCode::where([
                    ['email', $validated['email']],
                    ['code', $validated['code']],
                    ['is_used', false],
                    ['expires_at', '>', now()]
                ])->lockForUpdate()->first();

                if (!$resetCode) {
                    Cache::put($cacheKey, Cache::get($cacheKey, 0) + 1, now()->addMinutes(15));
                    throw new \Exception('Invalid or expired verification code.');
                }

                Cache::forget($cacheKey);

                $token = Str::random(64);
                $resetCode->update(['code' => $token]);

                return $token;
            });

            return $this->sendResponse([
                'token' => $result,
                'email' => $validated['email']
            ], 'Code verified successfully. You can now reset your password.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            $message = $e->getMessage() === 'Invalid or expired verification code.' 
                ? 'Invalid or expired verification code.'
                : 'An error occurred during code verification.' . $e->getMessage();
            return $this->sendError($message, [], 422);
        }
    }


    public function resetPassword(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'token' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ], [
                'email.exists' => 'User not found with this email address.',
            ]);

            $resetCode = PasswordResetCode::where([
                ['email', $validated['email']],
                ['code', $validated['token']],
                ['is_used', false],
                ['expires_at', '>', now()]
            ])->first();

            if (!$resetCode) {
                return $this->sendError('Invalid or expired reset token.', [], 422);
            }


            DB::transaction(function () use ($validated, $resetCode) {
                
                User::where('email', $validated['email'])
                    ->update(['password' => Hash::make($validated['password'])]);
                
                $resetCode->update(['is_used' => true]);
            });

            return $this->sendResponse([], 'Password has been reset successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the password reset process.' . $e->getMessage(), []);
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
