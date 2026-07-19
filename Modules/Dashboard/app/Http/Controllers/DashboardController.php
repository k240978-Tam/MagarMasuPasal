<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        return view('dashboard::index', [
            'summary' => $dashboard->summaryFor($request->user()),
        ]);
    }
}
