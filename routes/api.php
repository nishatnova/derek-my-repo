<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ContactController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Controllers\API\UserController;


Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);    
Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
Route::get('/users/{userId}/detail', [UserController::class, 'getUserDetails']);


Route::post('/contact-us', [ContactController::class, 'store']);


Route::middleware([JwtAuthMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);

    // Route::get('/user/profile', [UserController::class, 'getProfile']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);

    
    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {      
        Route::get('/users', [UserController::class, 'getUsers']);
        
    });

    Route::middleware(['role:user'])->group(function () {      
    
        
    });


   
});
