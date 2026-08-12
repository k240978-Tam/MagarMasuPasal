<?php

use App\Http\Controllers\LocaleController;

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

// Interface language switcher — available signed in or not.
Route::post('locale', LocaleController::class)->name('locale.update');
