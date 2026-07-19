<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\Api\ReportApiController;

Route::middleware(['auth:sanctum', 'throttle:api', 'can:reports.view'])->prefix('v1/reports')->name('api.v1.reports.')->group(function () {
    Route::get('sales', [ReportApiController::class, 'sales'])->name('sales');
});
