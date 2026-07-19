<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\DB;
use Modules\Analytics\Models\DailySalesAggregate;
use Modules\Analytics\Models\ProductPerformanceAggregate;
use Modules\Tenancy\Models\Branch;

/**
 * Rolls completed sales for one date into daily_sales_aggregates and
 * product_performance_aggregates. Idempotent (updateOrCreate per
 * branch+date / branch+product+date), so re-running for the same date —
 * e.g. after a late sale correction — is always safe.
 */
class AggregationService
{
    public function aggregateDate(string $date): void
    {
        Branch::withoutTenantScope()->each(function (Branch $branch) use ($date) {
            $this->aggregateBranchDate($branch, $date);
        });
    }

    public function aggregateBranchDate(Branch $branch, string $date): void
    {
        $sales = DB::table('sales')
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->selectRaw('COUNT(*) as tx_count, COALESCE(SUM(total_amount),0) as total_sales, COALESCE(SUM(tax_amount),0) as total_tax, COALESCE(SUM(discount_amount),0) as total_discount')
            ->first();

        $grossProfit = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $date)
            ->selectRaw('COALESCE(SUM(sale_items.line_total - (sale_items.cost_price_at_sale * sale_items.quantity)),0) as profit')
            ->value('profit');

        DailySalesAggregate::withoutTenantScope()->updateOrCreate(
            ['branch_id' => $branch->id, 'date' => $date],
            [
                'business_id' => $branch->business_id,
                'total_sales' => $sales->total_sales ?? 0,
                'total_tax' => $sales->total_tax ?? 0,
                'total_discount' => $sales->total_discount ?? 0,
                'transaction_count' => $sales->tx_count ?? 0,
                'gross_profit' => $grossProfit ?? 0,
            ],
        );

        $productRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $date)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.line_total - (sale_items.cost_price_at_sale * sale_items.quantity)) as profit')
            ->get();

        foreach ($productRows as $row) {
            ProductPerformanceAggregate::withoutTenantScope()->updateOrCreate(
                ['branch_id' => $branch->id, 'product_id' => $row->product_id, 'date' => $date],
                [
                    'business_id' => $branch->business_id,
                    'quantity_sold' => $row->qty,
                    'revenue' => $row->revenue,
                    'profit' => $row->profit,
                ],
            );
        }
    }
}
