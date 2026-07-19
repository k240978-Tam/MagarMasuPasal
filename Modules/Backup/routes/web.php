<?php

use Illuminate\Support\Facades\Route;
use Modules\Backup\Http\Controllers\BackupController;

Route::middleware(['auth', 'can:business.manage'])->prefix('backups')->name('backups.')->group(function () {
    Route::get('/', [BackupController::class, 'index'])->name('index');
    Route::post('/', [BackupController::class, 'store'])->name('store');
});
