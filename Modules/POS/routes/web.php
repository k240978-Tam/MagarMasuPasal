<?php

use Illuminate\Support\Facades\Route;
use Modules\POS\Http\Controllers\CartController;
use Modules\POS\Http\Controllers\PosScreenController;

Route::middleware('auth')->prefix('pos/terminals/{terminal:public_id}')->group(function () {
    Route::get('/', PosScreenController::class)->name('pos.screen');
    Route::get('cart', [CartController::class, 'show'])->name('pos.cart.show');
    Route::post('cart/start', [CartController::class, 'start'])->name('pos.cart.start');
    Route::post('cart/items', [CartController::class, 'addItem'])->name('pos.cart.items.add');
    Route::post('cart/scan', [CartController::class, 'scanBarcode'])->name('pos.cart.scan');
    Route::patch('cart/items/{product}', [CartController::class, 'updateItem'])->name('pos.cart.items.update');
    Route::delete('cart/items/{product}', [CartController::class, 'removeItem'])->name('pos.cart.items.remove');
    Route::patch('cart/discount', [CartController::class, 'setDiscount'])->name('pos.cart.discount');
    Route::patch('cart/customer', [CartController::class, 'setCustomer'])->name('pos.cart.customer');
    Route::post('cart/hold', [CartController::class, 'hold'])->name('pos.cart.hold');
    Route::get('held-bills', [CartController::class, 'heldBills'])->name('pos.heldbills.index');
    Route::post('cart/resume/{heldBill}', [CartController::class, 'resume'])->name('pos.cart.resume');
    Route::post('cart/checkout', [CartController::class, 'checkout'])->name('pos.cart.checkout');
    Route::post('cart/complete', [CartController::class, 'complete'])->name('pos.cart.complete');
});
