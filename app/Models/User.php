<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // public function getProfilePhotoAttribute($value)
    // {
    //     return $value ? $value : asset('profile_photos/user.png');
    // }

    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'email' => $this->email,
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getProfilePhotoAttribute($value)
    {
        // If the user has set a profile photo, return the full URL, otherwise return the default one
        if ($value && $value !== 'profile_photos/user.png') {
            // If it already starts with 'http', return as is
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            // Otherwise, add the asset path
            return asset($value);
        }
        
        return asset('profile_photos/user.png');
    }

    protected static function boot()
    {
        parent::boot();
       
        static::updating(function ($user) {
            if ($user->isDirty('profile_photo') && $user->getOriginal('profile_photo')) {
                $oldPhoto = $user->getOriginal('profile_photo');
                
                // Skip default photo deletion
                if ($oldPhoto && $oldPhoto !== 'profile_photos/user.png') {
                    // Extract storage path from the database value
                    $storagePath = str_replace('storage/', '', $oldPhoto);
                    
                    // Queue old file deletion
                    dispatch(function() use ($storagePath) {
                        if (Storage::disk('public')->exists($storagePath)) {
                            Storage::disk('public')->delete($storagePath);
                        }
                    })->afterResponse();
                }
            }
        });
    }


    

}
