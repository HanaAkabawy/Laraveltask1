<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::post('/auth/register', [AuthController::class, 'apiRegister']);
Route::post('/auth/login', [AuthController::class, 'apiLogin']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
// Protected routes
Route::middleware('auth.jwt')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'getUserProfile']);
    Route::post('/check-permission', [AuthController::class, 'checkPermission']);
    
    // User management (requires manage users permission)
    Route::middleware('permission:manage users')->group(function () {
        Route::get('/users', [AuthController::class, 'getAllUsers']);
        Route::get('/users/{id}', [AuthController::class, 'getUserById']);
        Route::put('/users/{id}', [AuthController::class, 'updateUser']);
        Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);
    });
    
    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::post('/users/{userId}/assign-role', [AuthController::class, 'assignRole']);
        Route::post('/users/{userId}/remove-role', [AuthController::class, 'removeRole']);
        Route::post('/roles', [AuthController::class, 'createRole']);
        Route::post('/permissions', [AuthController::class, 'createPermission']);
    });
});
