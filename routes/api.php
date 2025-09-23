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



Route::post('/contact-us', [ContactController::class, 'store']);


Route::middleware([JwtAuthMiddleware::class])->group(function () {

    
    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {      
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user/detail', [AuthController::class, 'getUserDetails']);
        Route::post('/update-password', [AuthController::class, 'updatePassword']);
        Route::post('/update-profile', [AuthController::class, 'updateProfile']);
        
    });



   
});
