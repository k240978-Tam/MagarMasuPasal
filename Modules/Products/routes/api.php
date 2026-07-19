<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\Api\ProductApiController;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1/products')->name('api.v1.products.')->group(function () {
    Route::get('/', [ProductApiController::class, 'index'])->name('index');
    Route::get('barcode/{code}', [ProductApiController::class, 'barcode'])->name('barcode');
    Route::get('{product}', [ProductApiController::class, 'show'])->name('show');
});
