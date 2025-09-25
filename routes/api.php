<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ContactController;
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

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);    
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);



Route::post('/contact-us', [ContactController::class, 'store']);


Route::middleware([JwtAuthMiddleware::class])->group(function () {

    
    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {      
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user/detail', [AuthController::class, 'getUserDetails']);
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);

        Route::get('/contact-list', [ContactController::class, 'index']);
        
    });



   
});
