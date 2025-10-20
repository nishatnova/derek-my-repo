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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ResponseTrait;

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'phone' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'zip_code' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:500'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ];

            $request->validate($rules, [
                'email.unique' => 'This email is already registered.',
                'password.confirmed' => 'Password confirmation does not match.',
            ]);

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'address' => $request->address,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'is_active' => true,
            ]);

            // Generate tokens
            JWTAuth::factory()->setTTL(2880); // 48 hours access token
            $accessToken = JWTAuth::fromUser($user);

            JWTAuth::factory()->setTTL(20160); // 14 days refresh token
            $refreshToken = JWTAuth::fromUser($user);

            return $this->sendResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
            ], 'Registration successful!', 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstErrorMessage = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessage, [], 422);

        } catch (\Exception $e) {
            Log::error('Error during registration: ' . $e->getMessage());
            return $this->sendError('Error during registration. Please try again.', [], 500);
        }
    }

    /**
     * Login with remember me functionality
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember_me' => 'nullable|boolean',
            ]);

            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return $this->sendError('Invalid credentials', [], 401);
            }

            if (!$user->is_active) {
                return $this->sendError('Your account has been deactivated. Please contact support.', [], 403);
            }

            // Set token TTL based on remember me
            $rememberMe = $request->boolean('remember_me', false);
            
            if ($rememberMe) {
                // Remember me: 30 days access token, 60 days refresh token
                JWTAuth::factory()->setTTL(43200); // 30 days in minutes
                $accessToken = JWTAuth::fromUser($user);
                
                JWTAuth::factory()->setTTL(86400);
                $refreshToken = JWTAuth::fromUser($user);
            } else {
                // Normal login: 48 hours access token, 14 days refresh token
                JWTAuth::factory()->setTTL(2880); // 48 hours
                $accessToken = JWTAuth::fromUser($user);
                
                JWTAuth::factory()->setTTL(20160); // 14 days
                $refreshToken = JWTAuth::fromUser($user);
            }

            return $this->sendResponse([
                'token' => $accessToken,
                'refresh_token' => $refreshToken,
                'remember_me' => $rememberMe,
            ], 'Login successful.');

        } catch (ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            Log::error('Error during login: ' . $e->getMessage());
            return $this->sendError('Error during login. Please try again.', [], 500);
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
                $user = User::where('email', $validated['email'])->first();

                // Update password
                $user->update(['password' => Hash::make($validated['password'])]);

                // Mark token used
                $resetCode->update(['is_used' => true]);

                // Clear Redis cache for this user
                Cache::forget("user:profile:{$user->id}");
            });

            return $this->sendResponse([], 'Password has been reset successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('An error occurred during the password reset process. ' . $e->getMessage(), []);
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $rules = [
                'current_password' => ['required', 'string'],
                'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), [], 422);
            }

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->sendError('Current password is incorrect.', [], 401);
            }

            // Update password using query builder for speed
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'password' => Hash::make($request->new_password),
                    'updated_at' => now()
                ]);

            // Clear user cache after password update
            Cache::forget("user:profile:{$user->id}");

            return $this->sendResponse([], 'Password updated successfully.');

        } catch (ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            Log::error('Error updating password: ' . $e->getMessage());
            return $this->sendError('Error updating password.', [], 500);
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

    /**
     * Update user's name and email.
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $data = $request->input('data') 
                ? json_decode($request->input('data'), true) 
                : $request->all();

            if (!$data) {
                return $this->sendError('Invalid data format', [], 400);
            }

            $rules = [
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $user->id],
                'phone' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', 'max:100'],
                'city' => ['nullable', 'string', 'max:100'],
                'state' => ['nullable', 'string', 'max:100'],
                'zip_code' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:500'],
            ];

            $validator = Validator::make($data, $rules);
            
            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), [], 422);
            }

            if ($request->hasFile('profile_photo')) {
                $fileValidator = Validator::make(
                    ['file' => $request->file('profile_photo')], 
                    ['file' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']]
                );
                
                if ($fileValidator->fails()) {
                    return $this->sendError($fileValidator->errors()->first(), [], 422);
                }
            }

            $updates = [];
            $allowedFields = ['name', 'email', 'phone', 'country', 'city', 'state', 'zip_code', 'address'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field]) && !is_null($data[$field])) {
                    $updates[$field] = trim($data[$field]);
                }
            }

            $oldPhotoPath = null;
            if ($request->hasFile('profile_photo')) {
                $oldPhotoPath = $user->getRawOriginal('profile_photo');
                
                try {
                    $file = $request->file('profile_photo');
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('profile_photos', $filename, 'public');
                    $updates['profile_photo'] = 'storage/' . $path;
                } catch (\Exception $e) {
                    Log::error('Failed to upload profile photo: ' . $e->getMessage());
                    return $this->sendError('Failed to upload profile photo.', [], 500);
                }
            }

            // Update user only if there are changes
            if (!empty($updates)) {
                // Use query builder for faster update
                DB::table('users')
                    ->where('id', $user->id)
                    ->update($updates + ['updated_at' => now()]);

                // Clear user cache after update
                Cache::forget("user:profile:{$user->id}");

                // Refresh user model
                $user->refresh();

                // Delete old photo asynchronously
                if ($oldPhotoPath && $oldPhotoPath !== 'profile_photos/user.png') {
                    dispatch(function() use ($oldPhotoPath) {
                        $storagePath = str_replace('storage/', '', $oldPhotoPath);
                        if (Storage::disk('public')->exists($storagePath)) {
                            Storage::disk('public')->delete($storagePath);
                        }
                    })->afterResponse();
                }
            }

            return $this->sendResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'country' => $user->country,
                'city' => $user->city,
                'state' => $user->state,
                'zip_code' => $user->zip_code,
                'address' => $user->address,
                'profile_photo' => $user->profile_photo,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ], 'Profile updated successfully.');

        } catch (ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (\Exception $e) {
            Log::error('Error updating profile: ' . $e->getMessage());
            return $this->sendError('Error updating profile.', [], 500);
        }
    }

    public function uploadProfilePhoto(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $validator = Validator::make($request->all(), [
                'profile_photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5048'], 
            ]);
            
            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), [], 422);
            }

            $oldPhotoPath = $user->getRawOriginal('profile_photo');
            
            try {
                $file = $request->file('profile_photo');
                $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('profile_photos', $filename, 'public');
                
                $newPhotoPath = 'storage/' . $path;
                
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'profile_photo' => $newPhotoPath,
                        'updated_at' => now()
                    ]);

                Cache::forget("user:profile:{$user->id}");

                $user->refresh();

                if ($oldPhotoPath && $oldPhotoPath !== 'profile_photos/user.png') {
                    dispatch(function() use ($oldPhotoPath) {
                        $storagePath = str_replace('storage/', '', $oldPhotoPath);
                        if (Storage::disk('public')->exists($storagePath)) {
                            Storage::disk('public')->delete($storagePath);
                        }
                    })->afterResponse();
                }

                return $this->sendResponse([
                    'profile_photo' => $user->profile_photo,
                ], 'Profile photo uploaded successfully.');

            } catch (\Exception $e) {
                Log::error('Failed to upload profile photo: ' . $e->getMessage());
                return $this->sendError('Failed to upload profile photo.', [], 500);
            }

        } catch (\Exception $e) {
            Log::error('Error in profile photo upload: ' . $e->getMessage());
            return $this->sendError('Error uploading profile photo.', [], 500);
        }
    }



    public function getUserDetails()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->sendError('User not authenticated.', [], 401);
            }

            // Cache key for this specific user
            $cacheKey = "user:profile:{$user->id}";

            // Try to get from cache first, cache for 10 minutes
            $userDetails = Cache::remember($cacheKey, 600, function () use ($user) {
                return User::select([
                    'id', 'name', 'email', 'phone', 'country', 'city', 
                    'state', 'zip_code', 'address', 'profile_photo', 
                    'role', 'is_active', 'email_verified_at', 'created_at'
                ])
                ->where('id', $user->id)
                ->first();
            });

            if (!$userDetails) {
                return $this->sendError('User not found.', [], 404);
            }

            return $this->sendResponse([
                'id' => $userDetails->id,
                'name' => $userDetails->name,
                'email' => $userDetails->email,
                'phone' => $userDetails->phone,
                'country' => $userDetails->country,
                'city' => $userDetails->city,
                'state' => $userDetails->state,
                'zip_code' => $userDetails->zip_code,
                'address' => $userDetails->address,
                'profile_photo' => $userDetails->profile_photo,
                'role' => $userDetails->role,
                'is_active' => $userDetails->is_active,
                'email_verified' => $userDetails->email_verified_at ? true : false,
                'member_since' => $userDetails->created_at->format('M Y'),
            ], 'Profile retrieved successfully.');

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->sendError('Token has expired.', [], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return $this->sendError('Invalid token.', [], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return $this->sendError('Token not provided.', [], 401);
        } catch (\Exception $e) {
            Log::error('Error fetching profile: ' . $e->getMessage());
            return $this->sendError('An unexpected error occurred while fetching profile.', [], 500);
        }
    }

    
}
