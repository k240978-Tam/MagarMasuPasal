<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\Api\DashboardApiController;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1/dashboard')->name('api.v1.dashboard.')->group(function () {
    Route::get('summary', [DashboardApiController::class, 'summary'])->name('summary');
});
