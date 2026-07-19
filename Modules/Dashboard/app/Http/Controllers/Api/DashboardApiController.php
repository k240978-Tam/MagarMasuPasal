<?php

namespace Modules\Dashboard\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\Services\DashboardService;

class DashboardApiController extends Controller
{
    use ApiResponds;

    public function summary(Request $request, DashboardService $dashboard): JsonResponse
    {
        return $this->respond($dashboard->summaryFor($request->user()));
    }
}
