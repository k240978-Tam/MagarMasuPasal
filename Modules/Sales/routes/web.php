<?php

use Illuminate\Support\Facades\Route;
use Modules\Sales\Http\Controllers\ReceiptController;

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/receipt', ReceiptController::class)->name('sales.receipt');
});
