<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\Api\SaleApiController;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1/sales')->name('api.v1.sales.')->group(function () {
    Route::get('/', [SaleApiController::class, 'index'])->name('index');
    Route::get('{sale}', [SaleApiController::class, 'show'])->name('show');
});
