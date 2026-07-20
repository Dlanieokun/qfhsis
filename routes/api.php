<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UsersController;
use App\Http\Controllers\Api\PhoReportController;

/*
|--------------------------------------------------------------------------
| API Gateway Route Blocks Configuration
|--------------------------------------------------------------------------
*/

// Public access point gateway routes
Route::post('/mobile/login', [UsersController::class, 'login']);
Route::post('/sync/database', [SyncController::class, 'syncFromAndroid']);
Route::post('/upload', [SyncController::class, 'uploadFromAndroid']);
Route::get('/sync/insert/{loop}', [UsersController::class, 'insertloop']);

Route::get('/hello', function () {
    return response()->json(['message' => 'Welcome to your Laravel 12 API!']);
});

// Location API Definitions
// Note: Laravel automatically prefixes routes in this file with '/api'
Route::get('/locations/regions', function () {
    return DB::table('regions')->orderBy('regDesc')->get();
});
Route::get('/locations/provinces/{regCode}', function ($regCode) {
    return DB::table('provinces')->where('regCode', $regCode)->orderBy('provDesc')->get();
});
Route::get('/locations/municipalities/{provCode}', function ($provCode) {
    return DB::table('municipalities')->where('provCode', $provCode)->orderBy('citymunDesc')->get();
});
Route::get('/locations/barangays/{munCode}', function ($munCode) {
    return DB::table('barangays')->where('citymunCode', $munCode)->orderBy('brgyDesc')->get();
});

Route::get('/family-planning/report', [PhoReportController::class, 'familyPlaning']);
Route::get('/maternal-care/report', [PhoReportController::class, 'maternalCare']);
Route::get('/child-care/report', [PhoReportController::class, 'childCare']);
Route::get('/oral-health/report', [PhoReportController::class, 'oralHealthCare']);
Route::get('/non-communicable-disease/report', [PhoReportController::class, 'nonCommunicableDisease']);
Route::get('/environmental-health/report', [PhoReportController::class, 'environmentalHealth']);
Route::get('/infectious-disease/report', [PhoReportController::class, 'infectiousDisease']);

// Guarded backend routes — require a valid Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UsersController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Pull is scoped server-side to the requesting user's assigned
    // barangay / municipality / province / region catchment area.
    Route::get('/sync/pull', [SyncController::class, 'syncToAndroid']);
});