<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UsersController;

/*
|--------------------------------------------------------------------------
| API Gateway Route Blocks Configuration
|--------------------------------------------------------------------------
*/

// Public access point gateway routes
Route::post('/mobile/login', [UsersController::class, 'login']);
Route::post('/sync/database', [SyncController::class, 'syncFromAndroid']);
Route::get('/sync/pull', [SyncController::class, 'syncToAndroid']);

Route::get('/hello', function () {
    return response()->json(['message' => 'Welcome to your Laravel 12 API!']);
});

// Guarded backend metrics domain profile routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UsersController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});