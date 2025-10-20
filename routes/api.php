<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Cache;


// Route::get('/test-phpredis', function () {
//     try {
//         // Test Laravel Cache facade
//         Cache::put('laravel_test_key', 'Hello from Laravel with PhpRedis!', 60);
//         $cacheValue = Cache::get('laravel_test_key');
        
//         // Test direct Redis connection
//         $redis = Cache::getRedis();
//         $redis->set('direct_test_key', 'Direct PhpRedis connection');
//         $directValue = $redis->get('direct_test_key');
        
//         return response()->json([
//             'status' => 'success', 
//             'cache_value' => $cacheValue,
//             'direct_value' => $directValue,
//             'redis_client' => get_class($redis),
//             'phpredis_version' => phpversion('redis')
//         ]);
        
//     } catch (Exception $e) {
//         return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
//     }
// });

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);    
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

Route::get('/product/{product_id}/detail', [ProductController::class, 'show']);
Route::get('/product/all-list', [ProductController::class, 'index']);



// Download product as PDF
Route::get('/products/{id}/download-pdf', [ProductController::class, 'productDownloadPDF']);

Route::post('/contact-us', [ContactController::class, 'store']);


Route::middleware([JwtAuthMiddleware::class])->group(function () {

    Route::get('/user/detail', [AuthController::class, 'getUserDetails']);
    // Upload profile photo
    Route::post('/profile/upload-photo', [AuthController::class, 'uploadProfilePhoto']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/product/{productId}/submit-purchase-form', [PurchaseController::class, 'submitPurchase']);

    Route::get('purchase/my-list', [PurchaseController::class, 'myPurchases']);
    Route::get('purchase/{purchaseId}/detail', [PurchaseController::class, 'show']);

    Route::post('/product/purchase/{purchaseId}/payment', [PaymentController::class, 'processPayment']);
    Route::get('/purchase/{purchaseId}/invoice-download', [PaymentController::class, 'downloadInvoice']);
    
    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {      
        Route::get('/dashboard/stats', [DashboardController::class, 'getDashboardStats']);
       
        Route::post('/product/create', [ProductController::class, 'store']);
        Route::post('/product/{product_id}/update', [ProductController::class, 'update']);
        Route::patch('/product/{product_id}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::get('/purchase/all-list', [PurchaseController::class, 'index']);
        Route::patch('/purchase/{purchaseId}/update/order-status', [PurchaseController::class, 'updateOrderStatus']);
        




        
    });



   
});
