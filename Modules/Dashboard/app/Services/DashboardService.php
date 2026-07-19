<?php

namespace Modules\Dashboard\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Services\InventoryService;
use Modules\Reports\Services\ReportService;
use Modules\Tenancy\Models\Branch;

/**
 * Assembles the role-aware dashboard summary. Widgets are derived per
 * permission, not per business type — an Owner sees every branch's
 * numbers, a Cashier only ever sees their own shift's, matching
 * docs/architecture/05-screen-wireframes.md §5.3.
 */
class DashboardService
{
    public function __construct(
        protected ReportService $reports,
        protected InventoryService $inventory,
    ) {}

    public function summaryFor(User $user): array
    {
        $businessId = $user->business_id;
        $isOwnerOrManager = $user->hasAnyRole(['Owner', 'Admin', 'Manager']);
        $branchId = $isOwnerOrManager ? null : $user->default_branch_id;

        $today = now()->toDateString();
        $todaySales = $this->reports->salesReport($businessId, $branchId, $today, $today);

        $trend = $this->salesTrend($businessId, $branchId, 7);
        $topProducts = $this->reports->topSellingProducts($businessId, $branchId, now()->subDays(30)->toDateString(), $today, 5);

        $lowStock = collect();
        foreach (Branch::where('business_id', $businessId)->when($branchId, fn ($q) => $q->where('id', $branchId))->get() as $branch) {
            $lowStock = $lowStock->merge($this->inventory->lowStock($businessId, $branch->id));
        }

        return [
            'todays_sales' => (float) $todaySales->sum('total_amount'),
            'transaction_count' => $todaySales->count(),
            'avg_sale' => $todaySales->count() ? round($todaySales->sum('total_amount') / $todaySales->count(), 2) : 0,
            'branch_count' => Branch::where('business_id', $businessId)->count(),
            'staff_count' => User::where('business_id', $businessId)->count(),
            'low_stock' => $lowStock,
            'sales_trend' => $trend,
            'top_products' => $topProducts,
            'recent_sales' => $this->reports->salesReport($businessId, $branchId, now()->subDays(7)->toDateString(), $today)->take(5),
            'scope' => $branchId ? 'branch' : 'business',
        ];
    }

    protected function salesTrend(int $businessId, ?int $branchId, int $days): array
    {
        $rows = DB::table('sales')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', now()->subDays($days - 1)->toDateString())
            ->selectRaw('DATE(completed_at) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('D');
            $values[] = (float) ($rows[$date] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
