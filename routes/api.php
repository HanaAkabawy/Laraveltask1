<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::post('/auth/register', [AuthController::class, 'apiRegister']);
Route::post('/auth/login', [AuthController::class, 'apiLogin']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/resend-confirmation', [AuthController::class, 'resendConfirmationEmail']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::get('users', [AuthController::class, 'getAllUsers']);
Route::get('users/{id}', [AuthController::class, 'getUserById']);
Route::put('users/{id}', [AuthController::class, 'updateUser']);
Route::delete('users/{id}', [AuthController::class, 'deleteUser']);

