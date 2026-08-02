<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\Api\CatalogController;
use Modules\Ecommerce\Http\Controllers\Api\OrderController;

Route::middleware('api')->prefix('v1/ecommerce')->name('api.v1.ecommerce.')->group(function () {
    Route::middleware('throttle:catalog')->prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])->name('index');
        Route::get('{product}', [CatalogController::class, 'show'])->name('show');
    });

    Route::middleware('throttle:order')->prefix('orders')->name('orders.')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::get('{order}', [OrderController::class, 'show'])->name('show');
    });
});
