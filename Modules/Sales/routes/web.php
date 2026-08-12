<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\InvoiceCancellationController;
use Modules\Sales\Http\Controllers\IrdReportController;
use Modules\Sales\Http\Controllers\ReceiptController;

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/receipt', ReceiptController::class)->name('sales.receipt');

    Route::middleware('can:reports.view')->prefix('ird')->name('ird.')->group(function () {
        Route::get('sales-book', [IrdReportController::class, 'salesBook'])->name('sales-book');
        Route::get('sales-book/export', [IrdReportController::class, 'export'])->name('sales-book.export');
    });

    // Cancelling an issued tax invoice reverses stock and ledger effects, so
    // it sits behind sales.manage rather than plain POS operation.
    Route::middleware('can:sales.manage')
        ->post('sales/{sale}/cancel', InvoiceCancellationController::class)
        ->name('sales.cancel');
});
