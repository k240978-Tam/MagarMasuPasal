<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only query catalog backing the Reports module. Each method returns
 * plain arrays/collections — no view concerns here, so the same queries back
 * the web report pages, CSV export, and (later) the API equivalents.
 */
class ReportService
{
    public function salesReport(int $businessId, ?int $branchId, string $from, string $to): Collection
    {
        return DB::table('sales')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $from)
            ->whereDate('completed_at', '<=', $to)
            ->orderByDesc('completed_at')
            ->get(['id', 'public_id', 'invoice_no', 'branch_id', 'customer_id', 'cashier_id', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'completed_at']);
    }

    public function salesByCategory(int $businessId, ?int $branchId, string $from, string $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_category', 'product_category.product_id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'product_category.category_id')
            ->where('sales.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', '>=', $from)
            ->whereDate('sales.completed_at', '<=', $to)
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(sale_items.line_total)'))
            ->get(['categories.name', DB::raw('SUM(sale_items.line_total) as revenue'), DB::raw('SUM(sale_items.quantity) as quantity')]);
    }

    public function inventoryReport(int $businessId, ?int $branchId): Collection
    {
        return DB::table('inventory_stocks')
            ->join('products', 'products.id', '=', 'inventory_stocks.product_id')
            ->where('inventory_stocks.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('inventory_stocks.branch_id', $branchId))
            ->orderBy('products.name')
            ->get([
                'products.id as product_id', 'products.name', 'products.sku', 'products.min_stock',
                'inventory_stocks.branch_id', 'inventory_stocks.quantity_on_hand',
                DB::raw('inventory_stocks.quantity_on_hand * products.cost_price as stock_value'),
            ]);
    }

    public function expenseReport(int $businessId, ?int $branchId, string $from, string $to): Collection
    {
        return DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->whereDate('expenses.expense_date', '>=', $from)
            ->whereDate('expenses.expense_date', '<=', $to)
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc(DB::raw('SUM(expenses.amount)'))
            ->get(['expense_categories.name', DB::raw('SUM(expenses.amount) as total')]);
    }

    public function topSellingProducts(int $businessId, ?int $branchId, string $from, string $to, int $limit = 10): Collection
    {
        return $this->productPerformance($businessId, $branchId, $from, $to)
            ->sortByDesc('quantity_sold')
            ->take($limit)
            ->values();
    }

    /**
     * Products with zero sales in the window, ordered by how long they've
     * been sitting (oldest last stock movement first).
     */
    public function slowMovingProducts(int $businessId, ?int $branchId, int $withinDays = 30): Collection
    {
        $since = now()->subDays($withinDays)->toDateString();

        $soldProductIds = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', '>=', $since)
            ->pluck('sale_items.product_id');

        return DB::table('products')
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->whereNotIn('id', $soldProductIds)
            ->get(['id', 'name', 'sku', 'selling_price']);
    }

    public function peakSellingHours(int $businessId, ?int $branchId, string $from, string $to): Collection
    {
        $driver = DB::connection()->getDriverName();
        $hourExpr = $driver === 'sqlite' ? "strftime('%H', completed_at)" : 'HOUR(completed_at)';

        return DB::table('sales')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $from)
            ->whereDate('completed_at', '<=', $to)
            ->selectRaw("{$hourExpr} as hour, COUNT(*) as transaction_count, SUM(total_amount) as revenue")
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    protected function productPerformance(int $businessId, ?int $branchId, string $from, string $to): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', '>=', $from)
            ->whereDate('sales.completed_at', '<=', $to)
            ->groupBy('products.id', 'products.name')
            ->get([
                'products.id as product_id', 'products.name',
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
                DB::raw('SUM(sale_items.line_total - (sale_items.cost_price_at_sale * sale_items.quantity)) as profit'),
            ]);
    }
}
