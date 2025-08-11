<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/all-users', function () {
    return \App\Models\User::all();
});

// Password reset page
Route::get('/reset-password', function () {
    return view('auth.reset-password');
})->name('password.reset');