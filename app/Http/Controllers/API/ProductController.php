<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ResponseTrait;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redis;

class ProductController extends Controller
{
    use ResponseTrait;
    // Cache key constants
    private const CACHE_PREFIX = 'product:';
    private const CACHE_LIST_PREFIX = 'products:list:';
    private const CACHE_TTL = 3600;


   public function index(Request $request)
    {
        try {
            $limit = $request->get('limit', 10);
            $page = $request->get('page', 1);
            $search = $request->get('search');
            $category = $request->get('category');
            $fabric = $request->get('fabric');
            $moq = $request->get('moq');
            $minPrice = $request->get('min_price');
            $maxPrice = $request->get('max_price');
            $isActive = $request->get('is_active');
            $sortBy = $request->get('sort_by'); // newest, price_low_high, price_high_low

            $cacheKey = self::CACHE_LIST_PREFIX . 
                "page_{$page}:limit_{$limit}:" .
                "search_" . md5($search ?? 'all') . ":" .
                "category_" . ($category ?? 'all') . ":" .
                "fabric_" . md5($fabric ?? 'all') . ":" .
                "moq_" . ($moq ?? 'all') . ":" .
                "price_" . ($minPrice ?? 'min') . "_" . ($maxPrice ?? 'max') . ":" .
                "active_" . ($isActive ?? 'all') . ":" .
                "sort_" . ($sortBy ?? 'newest');
            
            $cachedResult = Cache::remember($cacheKey, 300, function () use ($limit, $page, $search, $category, $fabric, $moq, $minPrice, $maxPrice, $isActive, $sortBy) {
                $productQuery = Product::query();

                if ($search) {
                    $productQuery->searchLike($search);
                }

                if ($category) {
                    $productQuery->byCategory($category);
                }

                if ($fabric) {
                    $productQuery->where('fabric', 'LIKE', "%{$fabric}%");
                }

                // MOQ filter with ranges: 25+, 50+, 100+, 500+
                if ($moq) {
                    switch ($moq) {
                        case '25':
                        case '25+':
                            $productQuery->whereBetween('minimum_quantity', [25, 49]);
                            break;
                        case '50':
                        case '50+':
                            $productQuery->whereBetween('minimum_quantity', [50, 99]);
                            break;
                        case '100':
                        case '100+':
                            $productQuery->whereBetween('minimum_quantity', [100, 499]);
                            break;
                        case '500':
                        case '500+':
                            $productQuery->where('minimum_quantity', '>=', 500);
                            break;
                    }
                }

                // Price range filter
                if ($minPrice !== null && $minPrice !== '') {
                    $productQuery->where('per_price', '>=', floatval($minPrice));
                }

                if ($maxPrice !== null && $maxPrice !== '') {
                    $productQuery->where('per_price', '<=', floatval($maxPrice));
                }

                if ($isActive !== null && $isActive !== '') {
                    $activeValue = filter_var($isActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($activeValue !== null) {
                        if ($activeValue) {
                            $productQuery->active();
                        } else {
                            $productQuery->where('is_active', false);
                        }
                    }
                }

                // Apply sorting
                switch ($sortBy) {
                    case 'price_low_high':
                        $productQuery->orderBy('per_price', 'asc');
                        break;
                    case 'price_high_low':
                        $productQuery->orderBy('per_price', 'desc');
                        break;
                    case 'newest':
                    default:
                        $productQuery->orderBy('created_at', 'desc');
                        break;
                }

                $products = $productQuery
                    ->paginate($limit, ['*'], 'page', $page);

                if ($products->isEmpty()) {
                    return null;
                }

                return [
                    'products' => $products->items(),
                    'meta' => [
                        'limit' => $products->perPage(),
                        'page' => $products->currentPage(),
                        'total' => $products->total(),
                        'last_page' => $products->lastPage(),
                    ],
                ];
            });

            if (!$cachedResult) {
                return $this->sendResponse( [],'No products found.');
            }

            return $this->sendResponse($cachedResult, 'Products retrieved successfully');

        } catch (\Exception $e) {
            Log::error("Error fetching products: " . $e->getMessage());
            return $this->sendError('An unexpected error occurred while fetching products. ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Store new product
     */
    public function store(Request $request)
    {
        
        try {
            $data = json_decode($request->input('data'), true);
            
            if (!$data) {
                return $this->sendError('Invalid JSON data provided', [], 422);
            }

            $jsonValidationRules = [
                'name' => 'required|string|max:255',
                'category' => 'required|string|in:' . implode(',', Product::CATEGORIES),
                'code' => 'required|string|max:50|unique:products,code',
                'description' => 'required|string',
                'fabric' => 'nullable|string',
                'minimum_quantity' => 'required|integer|min:1',
                'per_price' => 'required|numeric|min:0',
                'additional_discounts' => 'nullable|array|max:3',
                'additional_discounts.*.min_quantity' => 'required|integer|min:1',
                'additional_discounts.*.max_quantity' => 'required|integer|gt:additional_discounts.*.min_quantity',
                'additional_discounts.*.price' => 'required|numeric|min:0',
            ];

            $fileValidationRules = [
                'images' => 'required|array|min:1|max:10',
                'images.*' => 'file|mimes:jpeg,jpg,png,gif,webp|max:5120',
            ];

            $validator = Validator::make($data, $jsonValidationRules);

            if ($validator->fails()) {
                return $this->sendError('Validation error: ' . $validator->errors()->first(), [], 422);
            }

            $validated = $validator->validated();

            $fileValidator = Validator::make($request->all(), $fileValidationRules);

            if ($fileValidator->fails()) {
                return $this->sendError('File validation error: ' . $fileValidator->errors()->first(), [], 422);
            }

            if (isset($validated['additional_discounts']) && is_array($validated['additional_discounts'])) {
                $discounts = $validated['additional_discounts'];
                usort($discounts, fn($a, $b) => $a['min_quantity'] - $b['min_quantity']);
                
                for ($i = 0; $i < count($discounts) - 1; $i++) {
                    if ($discounts[$i]['max_quantity'] >= $discounts[$i + 1]['min_quantity']) {
                        return $this->sendError('Quantity discount ranges cannot overlap.', [], 422);
                    }
                }
            }

            DB::beginTransaction();

            $productImages = [];
            try {
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        $productImages[] = $image->store('products', 'public');
                    }
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Error uploading product images: " . $e->getMessage());
                return $this->sendError('Failed to upload product images: ' . $e->getMessage(), [], 500);
            }

            $additionalDiscounts = [];
            if (isset($validated['additional_discounts']) && is_array($validated['additional_discounts'])) {
                foreach ($validated['additional_discounts'] as $discount) {
                    $additionalDiscounts[] = [
                        'min_quantity' => (int)$discount['min_quantity'],
                        'max_quantity' => (int)$discount['max_quantity'],
                        'price' => (float)$discount['price']
                    ];
                }
                usort($additionalDiscounts, fn($a, $b) => $a['min_quantity'] - $b['min_quantity']);
            }

            $product = Product::create([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'code' => strtoupper($validated['code']),
                'description' => $validated['description'],
                'fabric' => $validated['fabric'] ?? null,
                'minimum_quantity' => $validated['minimum_quantity'],
                'per_price' => $validated['per_price'],
                'additional_discounts' => $additionalDiscounts,
                'images' => $productImages,
                'is_active' => true
            ]);

            DB::commit();

            // CLEAR ALL PRODUCT CACHES IMMEDIATELY
            $this->clearAllProductCaches();

            $productImagesUrls = $product->images_urls;

            $response = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'code' => $product->code,
                    'description' => $product->description,
                    'fabric' => $product->fabric,
                    'minimum_quantity' => $product->minimum_quantity,
                    'per_price' => number_format($product->per_price, 2),
                    'additional_discounts' => $product->additional_discounts,
                    'images' => $productImagesUrls,
                    'images_count' => count($productImages),
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at->format('d M Y, g:i A'),
                ]
            ];

            return $this->sendResponse($response, 'Product created successfully!', 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error("Validation error: " . $e->getMessage());
            return $this->sendError('Validation error: ' . $e->errors(), [], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if (!empty($productImages)) {
                foreach ($productImages as $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
            
            Log::error("Error during product creation: " . $e->getMessage());
            return $this->sendError('Unexpected error occurred during product creation: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update existing product
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->sendError('Product not found', [], 404);
        }

        try {
            $validated = [];
            
            if ($request->has('data') && !empty($request->input('data'))) {
                $data = json_decode($request->input('data'), true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->sendError('Invalid JSON data provided: ' . json_last_error_msg(), [], 422);
                }

                $validator = Validator::make($data, [
                    'name' => 'sometimes|string|max:255',
                    'category' => 'sometimes|string|in:' . implode(',', Product::CATEGORIES),
                    'code' => 'sometimes|string|max:50|unique:products,code,' . $id,
                    'description' => 'sometimes|string',
                    'fabric' => 'sometimes|string|nullable',
                    'minimum_quantity' => 'sometimes|integer|min:1',
                    'per_price' => 'sometimes|numeric|min:0',
                    'additional_discounts' => 'sometimes|array|max:3',
                    'additional_discounts.*.min_quantity' => 'required|integer|min:1',
                    'additional_discounts.*.max_quantity' => 'required|integer|gt:additional_discounts.*.min_quantity',
                    'additional_discounts.*.price' => 'required|numeric|min:0',
                    'is_active' => 'sometimes|boolean',
                    'deleted_images' => 'sometimes|array',
                    'deleted_images.*' => 'sometimes|string',
                ]);

                if ($validator->fails()) {
                    return $this->sendError('Validation error: ' . $validator->errors()->first(), [], 422);
                }

                $validated = $validator->validated();
            }

            if ($request->hasFile('images')) {
                $fileValidator = Validator::make($request->all(), [
                    'images' => 'sometimes|array|max:10',
                    'images.*' => 'file|mimes:jpeg,jpg,png,gif,webp|max:5120',
                ]);

                if ($fileValidator->fails()) {
                    return $this->sendError('File validation error: ' . $fileValidator->errors()->first(), [], 422);
                }
            }

            if (isset($validated['additional_discounts']) && is_array($validated['additional_discounts'])) {
                $discounts = $validated['additional_discounts'];
                usort($discounts, fn($a, $b) => $a['min_quantity'] - $b['min_quantity']);
                
                for ($i = 0; $i < count($discounts) - 1; $i++) {
                    if ($discounts[$i]['max_quantity'] >= $discounts[$i + 1]['min_quantity']) {
                        return $this->sendError('Quantity discount ranges cannot overlap.', [], 422);
                    }
                }
            }

            $hasValidData = !empty($validated) || 
                        $request->hasFile('images') ||
                        (isset($validated['deleted_images']) && !empty($validated['deleted_images']));
            
            if (!$hasValidData) {
                return $this->sendError('No data provided to update', [], 422);
            }

        } catch (\Exception $e) {
            Log::error("Validation error: " . $e->getMessage());
            return $this->sendError('Validation error: ' . $e->getMessage(), [], 422);
        }

        $productImages = $product->images ?? [];
        
        try {
            if (isset($validated['deleted_images']) && is_array($validated['deleted_images'])) {
                $deletedImages = $validated['deleted_images'];
                $updatedImages = [];
                
                foreach ($productImages as $existingImage) {
                    $imageUrl = asset('storage/' . $existingImage);
                    
                    if (!in_array($imageUrl, $deletedImages) && !in_array($existingImage, $deletedImages)) {
                        $updatedImages[] = $existingImage;
                    } else {
                        Storage::disk('public')->delete($existingImage);
                    }
                }
                
                $productImages = $updatedImages;
            }
            
            if ($request->hasFile('images')) {
                $newImages = [];
                foreach ($request->file('images') as $image) {
                    $newImages[] = $image->store('products', 'public');
                }
                
                $productImages = array_merge($productImages, $newImages);
            }
            
        } catch (\Exception $e) {
            Log::error("Error handling product images: " . $e->getMessage());
            return $this->sendError('Failed to handle product images: ' . $e->getMessage(), [], 500);
        }

        $additionalDiscounts = $product->additional_discounts;
        if (isset($validated['additional_discounts'])) {
            $additionalDiscounts = [];
            if (is_array($validated['additional_discounts'])) {
                foreach ($validated['additional_discounts'] as $discount) {
                    $additionalDiscounts[] = [
                        'min_quantity' => (int)$discount['min_quantity'],
                        'max_quantity' => (int)$discount['max_quantity'],
                        'price' => (float)$discount['price']
                    ];
                }
                usort($additionalDiscounts, fn($a, $b) => $a['min_quantity'] - $b['min_quantity']);
            }
        }

        try {
            $product->update([
                'name' => $validated['name'] ?? $product->name,
                'category' => $validated['category'] ?? $product->category,
                'code' => isset($validated['code']) ? strtoupper($validated['code']) : $product->code,
                'description' => $validated['description'] ?? $product->description,
                'fabric' => $validated['fabric'] ?? $product->fabric,
                'minimum_quantity' => $validated['minimum_quantity'] ?? $product->minimum_quantity,
                'per_price' => $validated['per_price'] ?? $product->per_price,
                'additional_discounts' => $additionalDiscounts,
                'images' => $productImages,
                'is_active' => $validated['is_active'] ?? $product->is_active,
            ]);

            $product->refresh();

            // CLEAR ALL PRODUCT CACHES IMMEDIATELY
            $this->clearAllProductCaches();

            $productImagesUrls = $product->images_urls;

            return $this->sendResponse([
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'code' => $product->code,
                    'description' => $product->description,
                    'fabric' => $product->fabric,
                    'minimum_quantity' => $product->minimum_quantity,
                    'per_price' => number_format($product->per_price, 2),
                    'additional_discounts' => $product->additional_discounts,
                    'images' => $productImagesUrls,
                    'images_count' => count($productImages),
                    'is_active' => $product->is_active,
                    'updated_at' => $product->updated_at->format('d M Y, g:i A'),
                ]
            ], 'Product updated successfully!');

        } catch (\Exception $e) {
            Log::error("Error during product update: " . $e->getMessage());
            return $this->sendError('Unexpected error occurred during product update: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Clear all product-related caches - UPDATED FIX
     */
    private function clearAllProductCaches(): void
    {
        try {
            // Method 1: Using Cache facade with pattern matching
            $redis = Redis::connection();
            
            // Get the exact cache prefix Laravel uses
            $laravelPrefix = config('cache.prefix', 'laravel_cache') . ':';
            
            Log::info("Cache prefix: " . $laravelPrefix);
            
            // Clear all product list caches
            $listPattern = $laravelPrefix . self::CACHE_LIST_PREFIX . '*';
            $listKeys = $redis->keys($listPattern);
            
            if (!empty($listKeys)) {
                $redis->del($listKeys);
                Log::info("✓ Cleared " . count($listKeys) . " product list cache keys");
            } else {
                Log::warning("No product list cache keys found with pattern: " . $listPattern);
            }
            
            // Clear individual product caches
            $productPattern = $laravelPrefix . self::CACHE_PREFIX . '*';
            $productKeys = $redis->keys($productPattern);
            
            if (!empty($productKeys)) {
                $redis->del($productKeys);
                Log::info("✓ Cleared " . count($productKeys) . " individual product cache keys");
            }
            
            // Method 2: Direct flush as backup (nuclear option)
            Cache::tags(['products'])->flush(); // If using cache tags
            
            Log::info("✓ Cache clearing completed successfully");
            
        } catch (\Exception $e) {
            Log::error("Cache clearing failed: " . $e->getMessage());
            
            // Last resort: clear everything
            try {
                Cache::flush();
                Log::warning("Used Cache::flush() as fallback");
            } catch (\Exception $flushError) {
                Log::error("Even Cache::flush() failed: " . $flushError->getMessage());
            }
        }
    }

    /**
     * Get product from cache or database
     */
    public function show($id)
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $id;
            
            $cachedProduct = Cache::get($cacheKey);
            
            if ($cachedProduct && !isset($cachedProduct['images'])) {
                // Invalid cache structure, clear it
                Cache::forget($cacheKey);
                $cachedProduct = null;
            }
            
            if (!$cachedProduct) {
                $product = Product::find($id);
                
                if (!$product) {
                    return $this->sendError('Product not found', [], 404);
                }
                
                $cachedProduct = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'code' => $product->code,
                    'description' => $product->description,
                    'fabric' => $product->fabric,
                    'minimum_quantity' => $product->minimum_quantity,
                    'per_price' => $product->per_price,
                    'additional_discounts' => $product->additional_discounts,
                    'images' => $product->images_urls, // Use the accessor
                    'is_active' => $product->is_active,
                ];
                
                Cache::put($cacheKey, $cachedProduct, self::CACHE_TTL);
            }

            return $this->sendResponse([
                'product' => [
                    'id' => $cachedProduct['id'],
                    'name' => $cachedProduct['name'],
                    'category' => $cachedProduct['category'],
                    'code' => $cachedProduct['code'],
                    'description' => $cachedProduct['description'],
                    'fabric' => $cachedProduct['fabric'],
                    'minimum_quantity' => $cachedProduct['minimum_quantity'],
                    'per_price' => number_format($cachedProduct['per_price'], 2),
                    'additional_discounts' => $cachedProduct['additional_discounts'],
                    'images' => $cachedProduct['images'],
                    'is_active' => $cachedProduct['is_active'],
                ]
            ], 'Product retrieved successfully');

        } catch (\Exception $e) {
            Log::error("Error retrieving product: " . $e->getMessage());
            return $this->sendError('Error retrieving product', [], 500);
        }
    }

    /**
     * Toggle product active status
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->sendError('Product not found.', [], 404);
            }

            // Toggle the is_active status
            $product->is_active = !$product->is_active;
            $product->save();

            Cache::forget(self::CACHE_PREFIX . $id);
            Cache::flush(); 

            $status = $product->is_active ? 'activated' : 'deactivated';

            return $this->sendResponse([
                'id' => $product->id,
                'is_active' => $product->is_active,
            ], "Product {$status} successfully.");

        } catch (\Exception $e) {
            Log::error("Error toggling product status: " . $e->getMessage());
            return $this->sendError('An error occurred while updating product status.', [], 500);
        }
    }


    /**
     * Download product details as PDF
     */
    public function productDownloadPDF($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->sendError('Product not found', [], 404);
            }

            $logoPath = public_path('images/logo.png');
            $logoBase64 = '';
            
            if (file_exists($logoPath)) {
                $logoBase64 = base64_encode(file_get_contents($logoPath));
                $logoMimeType = mime_content_type($logoPath);
            }
            $imagesBase64 = [];
            if ($product->images && is_array($product->images)) {
                foreach ($product->images as $imagePath) {
                    $fullPath = storage_path('app/public/' . $imagePath);
                    
                    if (file_exists($fullPath)) {
                        $imageData = file_get_contents($fullPath);
                        $mimeType = mime_content_type($fullPath);
                        $imagesBase64[] = "data:{$mimeType};base64," . base64_encode($imageData);
                    }
                }
            }

            $data = [
                'product' => $product,
                'images_base64' => $imagesBase64,
                'generated_at' => now()->format('d M Y, g:i A'),
                'logo' => $logoBase64 ? "data:{$logoMimeType};base64,{$logoBase64}" : null,
            ];

            // Load PDF view
            $pdf = PDF::loadView('pdf.product-details', $data);
            $pdf->setPaper('A4', 'portrait');
            
            $filename = 'product-' . $product->code . '-' . now()->format('Y-m-d') . '.pdf';
            
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error("Error generating product PDF: " . $e->getMessage());
            return $this->sendError('Error generating PDF: ' . $e->getMessage(), [], 500);
        }
    }
}