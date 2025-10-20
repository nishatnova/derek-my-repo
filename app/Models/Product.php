<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'code',
        'description',
        'fabric',
        'minimum_quantity',
        'per_price',
        'additional_discounts',
        'images',
        'is_active',
    ];

    protected $casts = [
        'images' => 'array',
        'additional_discounts' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = ['images_urls'];
    protected $hidden = ['images']; 

    public const CATEGORIES = [
        'football',
        'rugby',
        'cricket',
        'baseball',
        'aquatics',
        'martial_arts',
        'female_fitness',
        'team_apparel',
    ];

    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    public function getImagesUrlsAttribute()
    {
        return array_map(function ($imagePath) {
            return asset('storage/' . $imagePath); 
        }, $this->images ?: []);
    }

    /**
     * Scope for searching products
     */
    public function scopeSearchLike(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('code', 'LIKE', "%{$term}%")
              ->orWhere('category', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Scope for active products only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for products by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
