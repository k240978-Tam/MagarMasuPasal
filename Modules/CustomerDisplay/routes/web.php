<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerDisplay\Http\Controllers\DisplayScreenController;

/**
 * No 'auth' middleware — the Customer Display is an unattended screen with
 * no login (docs/architecture/05-screen-wireframes.md §5.2). The terminal's
 * ULID public_id is the only thing gating access, matching the public
 * broadcast channel it subscribes to (see routes/channels.php).
 */
Route::get('display/terminals/{terminal:public_id}', DisplayScreenController::class)->name('display.screen');
