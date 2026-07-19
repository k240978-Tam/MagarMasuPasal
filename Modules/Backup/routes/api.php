<?php

use Illuminate\Support\Facades\Route;
use Modules\Backup\Http\Controllers\BackupController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('backups', BackupController::class)->names('backup');
});
