<?php

use App\Http\Controllers\FhsisReportController;
use App\Http\Controllers\PhoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Clean root landing page redirection
Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

// FHSIS Secure Core Application Routes
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Core Dashboard Workspace View
    Route::get('/fhsis/dashboard', [FhsisReportController::class, 'index'])->name('fhsis.dashboard');
    
    // Process New Indicator Submissions
    Route::post('/fhsis/reports', [FhsisReportController::class, 'store'])->name('fhsis.reports.store');
    
    // Render Consolidated Regional Public Health Analytics View
    Route::get('/fhsis/reports', [FhsisReportController::class, 'generalReport'])->name('fhsis.reports');
    
    // File Streaming Download Interface (CSV Generation Payload)
    Route::get('/fhsis/reports/export', [FhsisReportController::class, 'export'])->name('fhsis.reports.export');
    Route::get('/fhsis/reports/export-fp', [FhsisReportController::class, 'familyPlanningDownload'])->name('fhsis.reports.familyPlanningDownload');
    Route::get('/fhsis/reports/export-mc', [FhsisReportController::class, 'maternalCareDownload'])->name('fhsis.reports.maternalCareDownload');
    Route::get('/fhsis/reports/export-ci', [FhsisReportController::class, 'childImmunicationDownload'])->name('fhsis.reports.childImmunicationDownload');
    Route::get('/fhsis/reports/export-cis', [FhsisReportController::class, 'childImmunicationSchoolDownload'])->name('fhsis.reports.childImmunicationSchoolDownload');
    Route::get('/fhsis/reports/export-cms', [FhsisReportController::class, 'childManagementSickDownload'])->name('fhsis.reports.childManagementSickDownload');
    Route::get('/fhsis/reports/export-cn', [FhsisReportController::class, 'childNutritionDownload'])->name('fhsis.reports.childNutritionDownload');
    Route::get('/fhsis/reports/export-filariasis', [FhsisReportController::class, 'filariasisRegistryDownload'])->name('fhsis.reports.filariasisRegistryDownload');
    Route::get('/fhsis/reports/export-leprosy', [FhsisReportController::class, 'leprosyRegistryDownload'])->name('fhsis.reports.leprosyRegistryDownload');
    Route::get('/fhsis/reports/export-schisto', [FhsisReportController::class, 'schistosomiasisRegistryDownload'])->name('fhsis.reports.schistosomiasisRegistryDownload');
    Route::get('/fhsis/reports/export-sth', [FhsisReportController::class, 'soilTransmittedHelminthiasisRegistryDownload'])->name('fhsis.reports.soilTransmittedHelminthiasisRegistryDownload');
    Route::get('/fhsis/reports/export-mh', [FhsisReportController::class, 'mentalHealthDownload'])->name('fhsis.reports.mentalHealthDownload');
    Route::get('/fhsis/reports/export-envi', [FhsisReportController::class, 'environmentalHealthDownload'])->name('fhsis.reports.environmentalHealthDownload');
    Route::get('/fhsis/reports/export-oral', [FhsisReportController::class, 'oralHealthCareDownload'])->name('fhsis.reports.oralHealthCareDownload');
    Route::get('/fhsis/reports/export-philpen', [FhsisReportController::class, 'philPENRiskAssessmentDownload'])->name('fhsis.reports.philPENRiskAssessmentDownload');
    Route::get('/fhsis/reports/export-eyes', [FhsisReportController::class, 'eyesScreeningDownload'])->name('fhsis.reports.eyesScreeningDownload');
    Route::get('/fhsis/reports/export-cervical', [FhsisReportController::class, 'cervicalCancerScreeningDownload'])->name('fhsis.reports.cervicalCancerScreeningDownload');
    Route::get('/fhsis/reports/export-geriatric', [FhsisReportController::class, 'geriatricScreeningDownload'])->name('fhsis.reports.geriatricScreeningDownload');
    
    
    // Fallback default routing context handler
    Route::get('dashboard', function () {
        return redirect()->route('fhsis.dashboard');
    })->name('dashboard');

    // User Management Routes
    Route::get('/fhsis/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/fhsis/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/fhsis/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/fhsis/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    
    Route::get('/fhsis/pho', [PhoController::class, 'pho'])->name('fhsis.pho');
    Route::get('/fhsis/public-nurse', [PhoController::class, 'nurse'])->name('fhsis.nurse');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';