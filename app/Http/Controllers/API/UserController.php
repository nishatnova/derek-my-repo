<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use ResponseTrait;

    // public function getProfile(Request $request)
    // {
    //     try {
    //         $user = User::find(Auth::id());

    //         if (!$user) {
    //             return $this->sendError('User not found.', [], 404);
    //         }

    //         $averageRating = 0;
    //         $totalReviews = 0;
    //         $isSubscribed = false;
    //         $updateDocument = false;

    //         if ($user->role === 'landlord') {
    //             // Get all properties of this landlord
    //             $properties = Property::where('user_id', $user->id)->get();

    //             // Get all reviews for this landlord's properties
    //             $reviews = PropertyReview::whereIn('property_id', $properties->pluck('id'))->get();

    //             // Calculate the total reviews and average rating
    //             $totalReviews = $reviews->count();
    //             if ($totalReviews > 0) {
    //                 $averageRating = $reviews->avg('rating');
    //             }

    //             $landlord = Landlord::where('user_id', $user->id)->first();
    //             if ($landlord && $landlord->subscription_status === 'active') {
    //                 $isSubscribed = true; 
    //             }

    //             if ($landlord && $landlord->insurance_document) {
    //                 $updateDocument = true;
    //             }
    //         }
    //         elseif ($user->role === 'tenant') {
    //             $reviews = TenantReview::where('tenant_id', $user->id)->get();

    //             // Calculate the total reviews and average rating
    //             $totalReviews = $reviews->count();
    //             if ($totalReviews > 0) {
    //                 $averageRating = $reviews->avg('rating');
    //             }

    //             $tenant = Tenant::where('user_id', $user->id)->first();
    //             if ($tenant && $tenant->subscription_status === 'active') {
    //                 $isSubscribed = true; 
    //             }
    //             if ($tenant && $tenant->insurance_document) {
    //                 $updateDocument = true;
    //             }
    //         }

    //         $isVerified = $user->hasVerifiedEmail();

    //         return $this->sendResponse([
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'email' => $user->email,
    //             'phone' => $user->phone,
    //             'profile_photo' => $user->profile_photo,
    //             'role' => $user->role,
    //             'about' => $user->about,
    //             "email_verified_at" => $user->email_verified_at,
    //             "is_verified" => $isVerified,
    //             "is_active" => $user->is_active,
    //             "created_at" => $user->created_at,
    //             "updated_at" => $user->updated_at,
    //             'average_rating' => $averageRating,
    //             'total_reviews' => $totalReviews,
    //             'is_subscribe' => $isSubscribed,
    //             'has_insurance_document' => $updateDocument,
    //         ], 'User profile retrieved successfully.');
    //     } catch (\Exception $e) {
    //         Log::error("Error fetching profile: " . $e->getMessage());
    //         return $this->sendError('Error fetching user profile.', [], 500);
    //     }
    // }



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
            
            // Parse JSON data efficiently
            $jsonData = $request->input('data');
            if (!$jsonData || !($data = json_decode($jsonData, true))) {
                 return $this->sendError('Invalid user data format', [], 400);
            }
            
            // Pre-compile validation rules (avoid string concatenation in validation)
            $rules = [
                'name'  => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'unique:users,email,' . $user->id],
                'phone' => ['nullable', 'string', 'max:20'],
            ];
            
            // Validate JSON data first
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                return $this->sendError($validator->errors()->first(), [], 400);
            }
            
            // Validate file separately only if exists
            if ($request->hasFile('profile_photo')) {
                $fileValidator = Validator::make(['file' => $request->file('profile_photo')], [
                    'file' => ['image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5240']
                ]);
                
                if ($fileValidator->fails()) {
                    return $this->sendError($fileValidator->errors()->first(), [], 400);
                }
            }
            
            // Build update array efficiently
            $updates = [];
            
            // Only add non-empty values to update array
            foreach (['name', 'email', 'phone'] as $field) {
                if (isset($data[$field]) && !empty(trim($data[$field]))) {
                    $updates[$field] = trim($data[$field]);
                }
            }
            
            // Handle file upload
            $oldPhotoPath = null;
            if ($request->hasFile('profile_photo')) {
                $oldPhotoPath = $user->getRawOriginal('profile_photo');
                
                // Generate unique filename
                $file = $request->file('profile_photo');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                
                // Store file
                $path = $file->storeAs('profile_photos', $filename, 'public');
                $updates['profile_photo'] = 'storage/' . $path;
            }
            
            // Perform single database update if there are changes
            if (!empty($updates)) {
                // Use query builder for faster updates 
                DB::table('users')
                    ->where('id', $user->id)
                    ->update($updates + ['updated_at' => now()]);
                
                
                // Update the current user instance
                foreach ($updates as $key => $value) {
                    $user->{$key} = $value;
                }
            }
            
            // Queue old photo deletion (non-blocking)
            if ($oldPhotoPath && $oldPhotoPath !== 'profile_photos/user.png') {
                dispatch(function() use ($oldPhotoPath) {
                    $storagePath = str_replace('storage/', '', $oldPhotoPath);
                    if (Storage::disk('public')->exists($storagePath)) {
                        Storage::disk('public')->delete($storagePath);
                    }
                })->afterResponse();
            }
            


            // Return minimal response

            return $this->sendResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo' => $user->profile_photo,
            ], 'Profile updated successfully.');
            
        } catch (ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 400);
        } catch (\Exception $e) {
             Log::error("Error updating profile: " . $e->getMessage());
             return $this->sendError('Error updating profile.' . $e->getMessage(), [], 500);
        }
    }


    public function getUserDetails($userId)
    {
        try {
            $user = User::findOrFail($userId);

            if (!$user) {
                return $this->sendError('User not found.', [], 404);
            }

            $userDetails = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_photo' => $user->profile_photo, // assuming profile_photo is stored publicly
                'role' => $user->role,
            ];

            return $this->sendResponse($userDetails, 'User details retrieved successfully.');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found: ' . $e->getMessage());
            return $this->sendError('User not found.', [], 404);
        } catch (\Exception $e) {
            Log::error("Unexpected error fetching user details: " . $e->getMessage());
            return $this->sendError('An unexpected error occurred while fetching user details. Please try again later.', [], 500);
        }
    }


    public function getUsers(Request $request)
    {
        try {
            $limit = $request->query('limit', 10); 
            $page = $request->query('page', 1);

            $query = User::where('role', 'user');


            $users = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            if ($users->isEmpty()) {
                return $this->sendError('No users found.', [], 404);
            }

            $users->getCollection()->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'profile_photo' => $user->profile_photo,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ];
            });

            return $this->sendResponse([
                'users' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'last_page' => $users->lastPage(),
                ],
            ], 'All reviews retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error("Model not found: " . $e->getMessage());
            return $this->sendError('No users found with the specified criteria.'. $e->getMessage(), [], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Database query error: " . $e->getMessage());
            return $this->sendError('Database error while fetching users. Please try again later.'. $e->getMessage(), [], 500);
        } catch (\Exception $e) {
            Log::error("Unexpected error fetching verified users: " . $e->getMessage());
            return $this->sendError('An unexpected error occurred while fetching users. Please try again later.'. $e->getMessage(), [], 500);
        }
    }


    
}
