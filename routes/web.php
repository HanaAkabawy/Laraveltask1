<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/all-users', function () {
    return \App\Models\User::all();
});