<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\ProductController;

Route::middleware(['auth', 'can:products.manage'])->prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('create', [ProductController::class, 'create'])->name('create');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::get('{product}/edit', [ProductController::class, 'edit'])->name('edit');
    Route::patch('{product}', [ProductController::class, 'update'])->name('update');
    Route::delete('{product}', [ProductController::class, 'destroy'])->name('destroy');
});
