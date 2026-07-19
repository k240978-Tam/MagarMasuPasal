<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportExportController;
use Modules\Reports\Http\Controllers\SalesReportController;

Route::middleware(['auth', 'can:reports.view'])->prefix('reports')->group(function () {
    Route::get('sales', SalesReportController::class)->name('reports.sales');
    Route::get('sales/export', [ReportExportController::class, 'salesCsv'])->name('reports.sales.export');
});
