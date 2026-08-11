<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\FinancialReportController;

Route::middleware(['auth', 'can:reports.view'])->prefix('accounting/reports')->name('accounting.reports.')->group(function () {
    Route::get('profit-loss', [FinancialReportController::class, 'profitLoss'])->name('profit-loss');
    Route::get('trial-balance', [FinancialReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('balance-sheet', [FinancialReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('cash-book', [FinancialReportController::class, 'cashBook'])->name('cash-book');
    Route::get('ledger', [FinancialReportController::class, 'ledger'])->name('ledger');
});
