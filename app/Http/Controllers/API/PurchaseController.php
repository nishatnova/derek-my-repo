<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PurchaseController extends Controller
{
    use ResponseTrait;

    private const CACHE_LIST_PREFIX = 'purchases:list:';
    private const DELIVERY_CHARGE = 20.00;

    public function submitPurchase(Request $request, $productId)
    {
        try {
            // Find product first
            $product = Product::find($productId);

            if (!$product) {
                return $this->sendError('Product not found', [], 404);
            }

            if (!$product->is_active) {
                return $this->sendError('This product is currently unavailable', [], 400);
            }

            // Get data from 'data' key and decode JSON
            $data = json_decode($request->input('data'), true);
            
            if (!$data) {
                return $this->sendError('Invalid JSON data provided', [], 422);
            }

            // Validation rules (without product_id)
            $validator = Validator::make($data, [
                'payment_type' => 'required|in:half,full',
                
                // Product Information (multiple items)
                'product_info' => 'required|array|min:1',
                'product_info.*.gender' => 'required|string',
                'product_info.*.size' => 'required|string',
                'product_info.*.pieces' => 'required|integer|min:1',
                'product_info.*.color' => 'nullable|string',
                
                
                // Delivery Information
                'organization_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'country' => 'required|string|max:100',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'zip_code' => 'required|string|max:20',
                'address' => 'required|string',
                
                // Additional Information
                'additional_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation error: ' . $validator->errors()->first(), [], 422);
            }

            $validated = $validator->validated();

            // Validate files
            $fileValidator = Validator::make($request->all(), [
                'logo_catalogue' => 'nullable|array|max:2',
                'logo_catalogue.*' => 'file|mimes:pdf,doc,docx,jpeg,jpg,png,gif,webp,zip|max:10240',
                'product_document' => 'nullable|file|mimes:pdf,doc,docx,jpeg,jpg,png,,xlsx,xls,ppt,pptx,zip|max:10240',
            ]);

            if ($fileValidator->fails()) {
                return $this->sendError('File validation error: ' . $fileValidator->errors()->first(), [], 422);
            }

            // Calculate total pieces
            $totalPieces = 0;
            foreach ($validated['product_info'] as $item) {
                $totalPieces += $item['pieces'];
            }

            // Check if meets minimum quantity
            if ($totalPieces < $product->minimum_quantity) {
                return $this->sendError(
                    "Minimum order quantity is {$product->minimum_quantity} pieces. You have {$totalPieces} pieces.",
                    [],
                    400
                );
            }

            // Calculate old price (original price without discount)
            $oldPrice = $totalPieces * $product->per_price;

            // Calculate price per piece based on quantity discounts
            $pricePerPiece = $this->calculatePricePerPiece($product, $totalPieces);

            // Calculate totals
            $productTotal = $totalPieces * $pricePerPiece;
            $deliveryCharge = self::DELIVERY_CHARGE;
            $grandTotal = $productTotal + $deliveryCharge;

            // Calculate payment amount based on type
            $paymentAmount = $validated['payment_type'] === 'half' 
                ? $grandTotal / 2 
                : $grandTotal;

            // Start database transaction
            DB::beginTransaction();

            try {
                // Handle file uploads
                $logoCatalogueFiles = [];
                if ($request->hasFile('logo_catalogue')) {
                    foreach ($request->file('logo_catalogue') as $file) {
                        $logoCatalogueFiles[] = $file->store('purchases/logos', 'public');
                    }
                }

                $productDocumentPath = null;
                if ($request->hasFile('product_document')) {
                    $productDocumentPath = $request->file('product_document')->store('purchases/documents', 'public');
                }

                // Create purchase record
                $purchase = Purchase::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'payment_type' => $validated['payment_type'],
                    
                    // Product details
                    'product_info' => $validated['product_info'],
                    'total_pieces' => $totalPieces,
                    
                    // Pricing
                    'price_per_piece' => $pricePerPiece,
                    'product_total' => $productTotal,
                    'delivery_charge' => $deliveryCharge,
                    'grand_total' => $grandTotal,
                    'payment_amount' => $paymentAmount,
                    
                    // Delivery information
                    'organization_name' => $validated['organization_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'country' => $validated['country'],
                    'city' => $validated['city'],
                    'state' => $validated['state'],
                    'zip_code' => $validated['zip_code'],
                    'address' => $validated['address'],
                    
                    // Additional
                    'additional_notes' => $validated['additional_notes'] ?? null,
                    'logo_catalogue' => $logoCatalogueFiles,
                    'product_document' => $productDocumentPath,
                    
                    // Status
                    'order_status' => 'pending',
                ]);

                DB::commit();

                // Prepare response
                $response = [
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_code' => $product->code,
                    'total_pieces' => $totalPieces,
                    'old_price' => number_format($oldPrice, 2), // Added this line
                    'price_per_piece' => number_format($pricePerPiece, 2),
                    'product_total' => number_format($productTotal, 2),
                    'delivery_charge' => number_format($deliveryCharge, 2),
                    'grand_total' => number_format($grandTotal, 2),
                    'payment_type' => $validated['payment_type'],
                    'payment_amount' => number_format($paymentAmount, 2),
                    'discount_applied' => $pricePerPiece < $product->per_price,
                    'order_status' => 'pending',
                ];

                return $this->sendResponse($response, 'Save info successfully!', 201);

            } catch (\Exception $e) {
                DB::rollBack();
                
                // Clean up uploaded files
                if (!empty($logoCatalogueFiles)) {
                    foreach ($logoCatalogueFiles as $file) {
                        Storage::disk('public')->delete($file);
                    }
                }
                if ($productDocumentPath) {
                    Storage::disk('public')->delete($productDocumentPath);
                }
                
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Error submitting purchase: " . $e->getMessage());
            return $this->sendError('Unexpected error occurred: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Calculate price per piece based on quantity and discounts
     */
    private function calculatePricePerPiece(Product $product, int $totalPieces): float
    {
        $pricePerPiece = $product->per_price;

        // Check if product has additional discounts
        $additionalDiscounts = $product->additional_discounts;
        
        if ($additionalDiscounts && is_array($additionalDiscounts) && !empty($additionalDiscounts)) {
            foreach ($additionalDiscounts as $discount) {
                $minQty = $discount['min_quantity'] ?? 0;
                $maxQty = $discount['max_quantity'] ?? 0;
                $discountPrice = $discount['price'] ?? 0;

                // Check if total pieces fall within this discount range
                if ($totalPieces >= $minQty && $totalPieces <= $maxQty) {
                    $pricePerPiece = $discountPrice;
                    break;
                }
            }

            // Handle if quantity exceeds the highest range
            // Get the last discount (highest quantity range)
            $lastDiscount = end($additionalDiscounts);
            if ($lastDiscount && $totalPieces > ($lastDiscount['max_quantity'] ?? 0)) {
                $pricePerPiece = $lastDiscount['price'] ?? $pricePerPiece;
            }
        }

        return (float) $pricePerPiece;
    }

    public function index(Request $request)
    {
        try {
            $limit = min((int) $request->get('limit', 10), 50);
            $page = (int) $request->get('page', 1);
            $orderStatus = $request->get('order_status');
            $paymentType = $request->get('payment_type');
            $search = $request->get('search');

            $query = DB::table('purchases as p')
                ->select([
                    'p.id',
                    'p.user_id',
                    'p.product_id',
                    'p.payment_id',
                    'p.payment_type',
                    'p.total_pieces',
                    'p.payment_amount',
                    'p.order_status',
                    'p.payment_status',
                    'p.created_at',
                    'p.updated_at',
                    'u.name as user_name',
                    'u.email as user_email',
                    'pr.code as product_code',
                    'pr.name as product_name',
                    'pr.category as product_category',
                    'pr.images as product_images'
                ])
                ->leftJoin('users as u', 'p.user_id', '=', 'u.id')
                ->leftJoin('products as pr', 'p.product_id', '=', 'pr.id')
                ->where('p.payment_status', 'paid');

            // Filter by order status
            if ($orderStatus) {
                $query->where('p.order_status', $orderStatus);
            }

            // Filter by payment type
            if ($paymentType) {
                $query->where('p.payment_type', $paymentType);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('u.name', 'LIKE', "%{$search}%")
                    ->orWhere('u.email', 'LIKE', "%{$search}%")
                    ->orWhere('pr.name', 'LIKE', "%{$search}%")
                    ->orWhere('pr.code', 'LIKE', "%{$search}%")
                    ->orWhere('p.payment_id', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('p.created_at', 'desc');

            $total = $query->count();

            if ($total === 0) {
                return $this->sendResponse([
                    'purchases' => [],
                    'meta' => [
                        'limit' => $limit,
                        'page' => 1,
                        'total' => 0,
                        'last_page' => 1,
                    ]
                ], 'No paid purchases found.');
            }

            $purchases = $query
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get();

            $items = $purchases->map(function ($p) {
                $images = json_decode($p->product_images, true);
                $imageUrls = null;
                if ($images && is_array($images)) {
                    $imageUrls = array_map(function($path) {
                        return asset('storage/' . $path);
                    }, $images);
                }

                return [
                    'id' => $p->id,
                    'payment_id' => $p->payment_id,
                    'payment_type' => $p->payment_type,
                    'total_pieces' => $p->total_pieces,
                    'payment_amount' => number_format($p->payment_amount, 2),
                    'order_status' => $p->order_status,
                    'product' => [
                        'name' => $p->product_name ?? 'N/A',
                        'code' => $p->product_code ?? 'N/A',
                        'category' => $p->product_category,
                        'images' => $imageUrls,
                    ],
                    'user' => [
                        'name' => $p->user_name ?? 'N/A',
                        'email' => $p->user_email ?? 'N/A',
                    ],
                    'purchased_at' => date('d M Y, g:i A', strtotime($p->created_at)),
                    'updated_at' => date('d M Y, g:i A', strtotime($p->updated_at)),
                ];
            });

            return $this->sendResponse([
                'purchases' => $items,
                'meta' => [
                    'limit' => $limit,
                    'page' => $page,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ], 'Purchases retrieved successfully');

        } catch (\Exception $e) {
            Log::error("Error fetching purchases: " . $e->getMessage());
            return $this->sendError('An unexpected error occurred while fetching purchases: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get user's own purchases (ultra-optimized with all fields)
     */
    public function myPurchases(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return $this->sendError('User not authenticated', [], 401);
        }

        $limit = min((int) $request->get('limit', 10), 50);
        $page = (int) $request->get('page', 1);

        // Raw SQL for maximum speed with ALL purchase fields
        $query = DB::table('purchases as p')
            ->select([
                'p.id',
                'p.user_id',
                'p.product_id',
                'p.payment_id',
                'p.payment_type',
                'p.product_info',
                'p.total_pieces',
                'p.price_per_piece',
                'p.product_total',
                'p.delivery_charge',
                'p.grand_total',
                'p.payment_amount',
                'p.organization_name',
                'p.email',
                'p.phone',
                'p.country',
                'p.city',
                'p.state',
                'p.zip_code',
                'p.address',
                'p.additional_notes',
                'p.logo_catalogue',
                'p.product_document',
                'p.order_status',
                'p.payment_status',
                'p.created_at',
                'p.updated_at',
                'pr.code as product_code',
                'pr.name as product_name',
                'pr.category as product_category',
                'pr.per_price as product_per_price', 
                'pr.images as product_images'
            ])
            ->leftJoin('products as pr', 'p.product_id', '=', 'pr.id')
            ->where('p.user_id', $user->id)
            ->where('p.payment_status', 'paid')
            ->orderBy('p.created_at', 'desc');

        // Get total count for pagination
        $total = $query->count();

        if ($total === 0) {
            return $this->sendResponse([
                'purchases' => [],
                'meta' => [
                    'limit' => $limit,
                    'page' => 1,
                    'total' => 0,
                    'last_page' => 1,
                ]
            ], 'No purchases found');
        }

        // Get paginated results
        $purchases = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        // Transformation with ALL fields
        $items = $purchases->map(function ($p) {

            $images = json_decode($p->product_images, true);
                $imageUrls = null;
                if ($images && is_array($images)) {
                    $imageUrls = array_map(function($path) {
                        return asset('storage/' . $path);
                    }, $images);
                }

             // Calculate old price (original price before discount)
            $oldPrice = $p->total_pieces * $p->product_per_price;

            return [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'product_id' => $p->product_id,
                'payment_id' => $p->payment_id,
                'payment_type' => $p->payment_type,
                'payment_status' => $p->payment_status,
                'order_status' => $p->order_status,
                
                // Product Information
                'product_info' => json_decode($p->product_info, true),
                'total_pieces' => $p->total_pieces,
                
                // Pricing
                'old_price' => number_format($oldPrice, 2), // Added this line
                'price_per_piece' => $p->price_per_piece,
                'product_total' => $p->product_total,
                'delivery_charge' => $p->delivery_charge,
                'grand_total' => $p->grand_total,
                'payment_amount' => $p->payment_amount,
                
                // Delivery Information
                'organization_name' => $p->organization_name,
                'email' => $p->email,
                'phone' => $p->phone,
                'country' => $p->country,
                'city' => $p->city,
                'state' => $p->state,
                'zip_code' => $p->zip_code,
                'address' => $p->address,
                
                // Additional Information
                'additional_notes' => $p->additional_notes,
                'logo_catalogue' => $p->logo_catalogue ? array_map(function($path) {
                    return asset('storage/' . $path);
                }, json_decode($p->logo_catalogue, true)) : null,
                'product_document' => $p->product_document ? asset('storage/' . $p->product_document) : null,
                
                // Product Details
                'product' => [
                    'id' => $p->product_id,
                    'code' => $p->product_code,
                    'name' => $p->product_name,
                    'category' => $p->product_category,
                    'images' => $imageUrls,
                ],
                
                // Timestamps
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
                'purchased_at' => $p->created_at,
            ];
        });

        return $this->sendResponse([
            'purchases' => $items,
            'meta' => [
                'limit' => $limit,
                'page' => $page,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ]
        ], 'Purchases retrieved successfully');
    }


    /**
     * Get single purchase details with all information
     */
    public function show($purchaseId)
    {
        try {
            // Raw SQL for maximum speed with ALL fields
            $purchase = DB::table('purchases as p')
                ->select([
                    'p.id',
                    'p.user_id',
                    'p.product_id',
                    'p.payment_id',
                    'p.payment_type',
                    'p.product_info',
                    'p.total_pieces',
                    'p.price_per_piece',
                    'p.product_total',
                    'p.delivery_charge',
                    'p.grand_total',
                    'p.payment_amount',
                    'p.organization_name',
                    'p.email',
                    'p.phone',
                    'p.country',
                    'p.city',
                    'p.state',
                    'p.zip_code',
                    'p.address',
                    'p.additional_notes',
                    'p.logo_catalogue',
                    'p.product_document',
                    'p.order_status',
                    'p.payment_status',
                    'p.created_at',
                    'p.updated_at',
                    'pr.id as product_id_full',
                    'pr.code as product_code',
                    'pr.name as product_name',
                    'pr.category as product_category',
                    'pr.fabric as product_fabric',
                    'pr.images as product_images',
                    'pr.per_price as product_per_price', // Added this line
                ])
                ->leftJoin('products as pr', 'p.product_id', '=', 'pr.id')
                ->where('p.id', $purchaseId)
                ->first();

            if (!$purchase) {
                return $this->sendError('Purchase not found or unauthorized', [], 404);
            }

            // Decode and convert product images to full URLs
            $productImages = json_decode($purchase->product_images, true);
            $productImageUrls = null;
            if ($productImages && is_array($productImages)) {
                $productImageUrls = array_map(function($path) {
                    return asset('storage/' . $path);
                }, $productImages);
            }

            // Decode and convert logo catalogue to full URLs
            $logoCatalogue = null;
            if ($purchase->logo_catalogue) {
                $logos = json_decode($purchase->logo_catalogue, true);
                if ($logos && is_array($logos)) {
                    $logoCatalogue = array_map(function($path) {
                        return asset('storage/' . $path);
                    }, $logos);
                }
            }

            // Calculate old price (original price before discount)
            $oldPrice = $purchase->total_pieces * $purchase->product_per_price;

            // Build complete purchase details
            $purchaseDetails = [
                // Purchase Basic Info
                'id' => $purchase->id,
                'payment_id' => $purchase->payment_id,
                'payment_type' => $purchase->payment_type,
                'payment_status' => $purchase->payment_status,
                'order_status' => $purchase->order_status,
                
                // Product Information
                'product_info' => json_decode($purchase->product_info, true),
                'total_pieces' => $purchase->total_pieces,
                
                // Pricing Details
                'old_price' => number_format($oldPrice, 2), // Added this line
                'price_per_piece' => number_format($purchase->price_per_piece, 2),
                'product_total' => number_format($purchase->product_total, 2),
                'delivery_charge' => number_format($purchase->delivery_charge, 2),
                'grand_total' => number_format($purchase->grand_total, 2),
                'payment_amount' => number_format($purchase->payment_amount, 2),
                
                // Delivery Information
                'organization_name' => $purchase->organization_name,
                'email' => $purchase->email,
                'phone' => $purchase->phone,
                'country' => $purchase->country,
                'city' => $purchase->city,
                'state' => $purchase->state,
                'zip_code' => $purchase->zip_code,
                'address' => $purchase->address,
                
                // Additional Information
                'additional_notes' => $purchase->additional_notes,
                'logo_catalogue' => $logoCatalogue,
                'product_document' => $purchase->product_document ? asset('storage/' . $purchase->product_document) : null,
                
                // Product Details
                'product' => [
                    'id' => $purchase->product_id,
                    'code' => $purchase->product_code,
                    'name' => $purchase->product_name,
                    'category' => $purchase->product_category,
                    'fabric' => $purchase->product_fabric,
                    'images' => $productImageUrls,
                ],
                
                
                // Timestamps
                'created_at' => $purchase->created_at,
            ];

            return $this->sendResponse([
                'purchase' => $purchaseDetails
            ], 'Purchase details retrieved successfully');

        } catch (\Exception $e) {
            Log::error("Error fetching purchase details", [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('Failed to retrieve purchase details', [], 500);
        }
    }


    /**
     * Update order status for a purchase
     */
    public function updateOrderStatus(Request $request, $purchaseId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'order_status' => 'required|in:pending,in-progress,completed,cancelled',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: ' . $validator->errors()->first(), [], 422);
            }

            $newStatus = $request->order_status;

            // Check if purchase exists and user is authorized
            $purchase = DB::table('purchases')
                ->select('id', 'user_id', 'order_status', 'payment_status')
                ->where('id', $purchaseId)
                ->first();

            if (!$purchase) {
                return $this->sendError('Purchase not found or unauthorized', [], 404);
            }

            // Check if payment is completed
            if ($purchase->payment_status !== 'paid') {
                return $this->sendError('Cannot update order status. Payment not completed.', [], 400);
            }

            // Check if status is already the same
            if ($purchase->order_status === $newStatus) {
                return $this->sendResponse([
                    'purchase_id' => $purchase->id,
                    'order_status' => $purchase->order_status,
                ], 'Order status is already ' . $newStatus);
            }

            // Update the order status
            $updated = DB::table('purchases')
                ->where('id', $purchaseId)
                ->update([
                    'order_status' => $newStatus,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info("Order status updated", [
                    'purchase_id' => $purchaseId,
                    'old_status' => $purchase->order_status,
                    'new_status' => $newStatus,
                ]);

                return $this->sendResponse([
                    'purchase_id' => $purchaseId,
                    'order_status' => $newStatus,
                ], 'Order status updated successfully');
            }

            return $this->sendError('Failed to update order status', [], 500);

        } catch (\Exception $e) {
            Log::error("Error updating order status", [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'purchase_id' => $purchaseId,
            ]);
            return $this->sendError('Failed to update order status', [], 500);
        }
    }


    

}
