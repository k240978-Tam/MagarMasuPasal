<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Auth\LoginController;
use Modules\UserManagement\Http\Controllers\Auth\TwoFactorChallengeController;
use Modules\UserManagement\Http\Controllers\TwoFactorController;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])
        ->middleware('throttle:two-factor')->name('two-factor.verify');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::prefix('account/two-factor')->name('two-factor.')->group(function () {
        Route::get('/', [TwoFactorController::class, 'show'])->name('show');
        Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
        Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
        Route::delete('/', [TwoFactorController::class, 'disable'])->name('disable');
        Route::post('recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
    });
});
