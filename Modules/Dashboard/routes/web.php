<?php

// The real dashboard route (GET /dashboard -> DashboardController, an
// invokable single-action controller) lives in the root routes/web.php.
// This file used to also register a Route::resource('dashboards', ...)
// left over from the original `module:make` scaffold — a plural,
// full-CRUD resource route pointing at a controller that only has
// __invoke(), so GET /dashboards would 500 if anything ever hit it.
// Found and removed during the Phase 8 launch review.
