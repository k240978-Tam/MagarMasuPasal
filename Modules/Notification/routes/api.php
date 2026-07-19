<?php

use Illuminate\Support\Facades\Route;
use Modules\Notification\Http\Controllers\Api\NotificationApiController;

Route::middleware(['auth:sanctum', 'throttle:api'])->prefix('v1/notifications')->name('api.v1.notifications.')->group(function () {
    Route::get('/', [NotificationApiController::class, 'index'])->name('index');
    Route::patch('{id}/read', [NotificationApiController::class, 'markRead'])->name('read');
});
