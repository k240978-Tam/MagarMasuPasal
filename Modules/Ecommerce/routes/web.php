<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\OnlineOrderController;

Route::middleware(['auth', 'can:sales.manage'])->prefix('online-orders')->name('online-orders.')->group(function () {
    Route::get('/', [OnlineOrderController::class, 'index'])->name('index');
    Route::get('{order}', [OnlineOrderController::class, 'show'])->name('show');
    Route::patch('{order}/status', [OnlineOrderController::class, 'updateStatus'])->name('update-status');
});
