<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\Controllers\Api\CustomerApiController;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1/customers')->name('api.v1.customers.')->group(function () {
    Route::get('/', [CustomerApiController::class, 'index'])->name('index');
    Route::get('{customer}/dues', [CustomerApiController::class, 'dues'])->name('dues');
});
