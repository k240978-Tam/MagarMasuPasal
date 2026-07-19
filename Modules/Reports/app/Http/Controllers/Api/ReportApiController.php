<?php

namespace Modules\Reports\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Reports\Services\ReportService;

class ReportApiController extends Controller
{
    use ApiResponds;

    public function sales(Request $request, ReportService $reports): JsonResponse
    {
        $businessId = $request->user()->business_id;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $branchId = $request->integer('branch_id') ?: null;

        $sales = $reports->salesReport($businessId, $branchId, $from, $to);

        return $this->respond([
            'total_sales' => (float) $sales->sum('total_amount'),
            'transaction_count' => $sales->count(),
            'top_products' => $reports->topSellingProducts($businessId, $branchId, $from, $to),
            'by_category' => $reports->salesByCategory($businessId, $branchId, $from, $to),
        ], meta: ['from' => $from, 'to' => $to]);
    }
}
