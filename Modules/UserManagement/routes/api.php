<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\Api\ApiAuthController;

Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    Route::post('login', [ApiAuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [ApiAuthController::class, 'logout'])->name('logout');
        Route::get('me', [ApiAuthController::class, 'me'])->name('me');
    });
});
