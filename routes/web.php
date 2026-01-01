<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JotFormController;

Route::get('/', function () {
    return view('welcome');
});

// JotForm API Routes
Route::prefix('jotform')->group(function () {
    // API endpoint untuk sync submissions (JSON response)
    Route::post('/sync', [JotFormController::class, 'sync']);

    // Get all synced submissions
    Route::get('/submissions', [JotFormController::class, 'index']);

    // Get form details
    Route::get('/form-details', [JotFormController::class, 'formDetails']);

    // Get sync statistics
    Route::get('/stats', [JotFormController::class, 'stats']);

    // Web endpoint untuk sync dari browser
    Route::get('/sync', [JotFormController::class, 'syncWeb']);
});
