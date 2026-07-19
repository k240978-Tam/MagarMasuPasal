<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\NotificationCenterController;

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
    Route::post('{id}/read', [NotificationCenterController::class, 'markRead'])->name('read');
    Route::post('read-all', [NotificationCenterController::class, 'markAllRead'])->name('read-all');
});
