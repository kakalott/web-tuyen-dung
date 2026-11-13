<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

Route::prefix('v1')->group(function () {
    
    // API-AUTH-01 & API-AUTH-02 (Public, Rate Limited)
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Các Route Yêu Cầu Đăng Nhập
    Route::middleware(['auth:sanctum'])->group(function () {
        // API-AUTH-03
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        // API-AUTH-04 (Yêu cầu tài khoản không bị khóa)
        Route::middleware(['active_account'])->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            
            // (Các tính năng Phase sau sẽ được đặt vào đây)
        });
    });
});
