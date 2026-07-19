<?php

use Illuminate\Support\Facades\Route;
use Modules\POS\Http\Controllers\Api\PosSaleApiController;

Route::middleware(['auth:sanctum', 'throttle:pos-api'])
    ->prefix('v1/pos/terminals/{terminal:public_id}')
    ->name('api.v1.pos.')
    ->group(function () {
        Route::post('sales', [PosSaleApiController::class, 'store'])->name('sales.store');
    });
