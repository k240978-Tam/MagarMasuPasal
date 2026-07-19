<?php

namespace Modules\Reports\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Modules\Reports\Services\ReportService;

class ReportExportController
{
    public function salesCsv(Request $request, ReportService $reports)
    {
        $businessId = $request->user()->business_id;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $branchId = $request->integer('branch_id') ?: null;

        $sales = $reports->salesReport($businessId, $branchId, $from, $to);

        $csv = "Invoice,Date,Subtotal,Discount,Tax,Total\n";

        foreach ($sales as $sale) {
            $csv .= implode(',', [
                $sale->invoice_no,
                $sale->completed_at,
                $sale->subtotal,
                $sale->discount_amount,
                $sale->tax_amount,
                $sale->total_amount,
            ])."\n";
        }

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=sales-report-{$from}-to-{$to}.csv",
        ]);
    }
}
