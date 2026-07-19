<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Categories\Models\Category;
use Modules\Products\Models\Product;
use Modules\Reports\Services\ReportService;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Tests\TestCase;

/**
 * Proves the report queries reconcile against hand-computed numbers, and
 * that the date-range/branch filters behave the way the Sales Report page
 * relies on (whereDate boundaries, branch scoping, category joins).
 */
class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected array $terminals = [];

    protected ?User $cashier = null;

    protected function terminalFor(int $businessId, int $branchId): BranchTerminal
    {
        return $this->terminals[$branchId] ??= BranchTerminal::create([
            'business_id' => $businessId, 'branch_id' => $branchId, 'name' => 'Counter 1',
        ]);
    }

    protected function cashierFor(int $businessId): User
    {
        return $this->cashier ??= User::factory()->create(['business_id' => $businessId]);
    }

    protected function makeSale(int $businessId, int $branchId, Product $product, float $qty, float $unitPrice, float $cost, string $completedAt): Sale
    {
        $sale = Sale::create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'terminal_id' => $this->terminalFor($businessId, $branchId)->id,
            'cashier_id' => $this->cashierFor($businessId)->id,
            'invoice_no' => 'INV-'.random_int(100000, 999999),
            'subtotal' => $qty * $unitPrice,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $qty * $unitPrice,
            'status' => 'completed',
            'sale_type' => 'retail',
            'completed_at' => $completedAt,
        ]);

        SaleItem::create([
            'business_id' => $businessId,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'cost_price_at_sale' => $cost,
            'line_total' => $qty * $unitPrice,
        ]);

        return $sale;
    }

    public function test_sales_report_filters_by_date_range_and_branch(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        $this->makeSale($businessId, $branchA->id, $product, 2, 480, 400, now()->toDateString().' 09:00:00');
        $this->makeSale($businessId, $branchA->id, $product, 1, 480, 400, now()->subDays(10)->toDateString().' 09:00:00');
        $this->makeSale($businessId, $branchB->id, $product, 3, 480, 400, now()->toDateString().' 10:00:00');

        $report = app(ReportService::class);

        $today = now()->toDateString();
        $allBranches = $report->salesReport($businessId, null, $today, $today);
        $this->assertCount(2, $allBranches, 'Only today\'s sales across both branches should be included.');
        $this->assertEqualsWithDelta(2400.0, $allBranches->sum('total_amount'), 0.01);

        $branchAOnly = $report->salesReport($businessId, $branchA->id, $today, $today);
        $this->assertCount(1, $branchAOnly);
        $this->assertEqualsWithDelta(960.0, $branchAOnly->sum('total_amount'), 0.01);
    }

    public function test_sales_by_category_groups_revenue_across_products(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);

        $meat = Category::create(['business_id' => $businessId, 'name' => 'Meat', 'slug' => 'meat']);
        $veg = Category::create(['business_id' => $businessId, 'name' => 'Vegetables', 'slug' => 'vegetables']);

        $chicken = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        $chicken->categories()->attach($meat->id);

        $tomato = Product::create(['business_id' => $businessId, 'name' => 'Tomato', 'unit_id' => $unit->id, 'selling_price' => 80]);
        $tomato->categories()->attach($veg->id);

        $today = now()->toDateString();
        $this->makeSale($businessId, $branch->id, $chicken, 2, 480, 400, $today.' 09:00:00');
        $this->makeSale($businessId, $branch->id, $tomato, 5, 80, 60, $today.' 09:30:00');

        $byCategory = app(ReportService::class)->salesByCategory($businessId, null, $today, $today);

        $this->assertEqualsWithDelta(960.0, $byCategory->firstWhere('name', 'Meat')->revenue, 0.01);
        $this->assertEqualsWithDelta(400.0, $byCategory->firstWhere('name', 'Vegetables')->revenue, 0.01);
    }

    public function test_top_selling_products_orders_by_quantity_sold(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'conversion_factor' => 1]);

        $eggs = Product::create(['business_id' => $businessId, 'name' => 'Eggs', 'unit_id' => $unit->id, 'selling_price' => 20]);
        $chicken = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        $today = now()->toDateString();
        $this->makeSale($businessId, $branch->id, $eggs, 30, 20, 15, $today.' 09:00:00');
        $this->makeSale($businessId, $branch->id, $chicken, 2, 480, 400, $today.' 09:00:00');

        $top = app(ReportService::class)->topSellingProducts($businessId, null, $today, $today);

        $this->assertSame('Eggs', $top->first()->name, 'Eggs sold 30 units, more than Chicken\'s 2, so it must rank first.');
    }

    public function test_peak_selling_hours_buckets_transactions_by_hour(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Eggs', 'unit_id' => $unit->id, 'selling_price' => 20]);

        $today = now()->toDateString();
        $this->makeSale($businessId, $branch->id, $product, 1, 20, 15, $today.' 09:15:00');
        $this->makeSale($businessId, $branch->id, $product, 1, 20, 15, $today.' 09:45:00');
        $this->makeSale($businessId, $branch->id, $product, 1, 20, 15, $today.' 18:00:00');

        $peak = app(ReportService::class)->peakSellingHours($businessId, null, $today, $today);

        $nineAm = $peak->firstWhere('hour', '09');
        $this->assertSame(2, (int) $nineAm->transaction_count);
        $sixPm = $peak->firstWhere('hour', '18');
        $this->assertSame(1, (int) $sixPm->transaction_count);
    }
}
