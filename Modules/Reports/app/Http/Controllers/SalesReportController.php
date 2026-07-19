<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Reports\Services\ReportService;
use Modules\Tenancy\Models\Branch;

class SalesReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        $businessId = $request->user()->business_id;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $branchId = $request->integer('branch_id') ?: null;

        $sales = $reports->salesReport($businessId, $branchId, $from, $to);
        $byCategory = $reports->salesByCategory($businessId, $branchId, $from, $to);
        $topProducts = $reports->topSellingProducts($businessId, $branchId, $from, $to, 5);
        $peakHours = $reports->peakSellingHours($businessId, $branchId, $from, $to);

        return view('reports::sales', [
            'branches' => Branch::where('business_id', $businessId)->get(),
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'sales' => $sales,
            'byCategory' => $byCategory,
            'topProducts' => $topProducts,
            'peakHours' => $peakHours,
            'totalSales' => $sales->sum('total_amount'),
            'transactionCount' => $sales->count(),
        ]);
    }
}
