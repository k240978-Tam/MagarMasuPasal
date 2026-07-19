<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\StockTransferController;

Route::middleware(['auth', 'can:inventory.manage'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('transfers', [StockTransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');
});
