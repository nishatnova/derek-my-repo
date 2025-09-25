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
        'minimum_quantity',
        'per_price',
        'colors',
        'sizes',
        'additional_discounts',
        'photos',
        'is_active',
    ];

    protected $casts = [
        'colors' => 'array',
        'sizes' => 'array',
        'photos' => 'array',
        'is_active' => 'boolean',
    ];

    public const CATEGORIES = [
        'football',
        'soccer',
        'basketball',
        'tennis',
        'swimming',
        'ice_skating',
        'cheerleader',
        'referee',
        'martial_arts',
        'cycling',
    ];

    public static function getCategories(): array
    {
        return self::CATEGORIES;
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
