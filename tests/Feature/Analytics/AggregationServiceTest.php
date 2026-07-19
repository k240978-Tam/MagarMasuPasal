<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use Modules\Analytics\Models\DailySalesAggregate;
use Modules\Analytics\Models\ProductPerformanceAggregate;
use Modules\Analytics\Services\AggregationService;
use Modules\Products\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the nightly rollup matches hand-computed totals and, critically,
 * is idempotent — re-running for the same date (e.g. after a late sale
 * correction) must update rows in place rather than double-count them.
 */
class AggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_date_matches_hand_computed_totals_and_is_idempotent(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        $date = now()->toDateString();
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $cashier = User::factory()->create(['business_id' => $businessId]);

        $sale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
            'cashier_id' => $cashier->id,
            'invoice_no' => 'INV-000001', 'subtotal' => 960, 'discount_amount' => 0,
            'tax_amount' => 60, 'total_amount' => 1020, 'status' => 'completed',
            'sale_type' => 'retail', 'completed_at' => $date.' 10:00:00',
        ]);

        SaleItem::create([
            'business_id' => $businessId, 'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price' => 480, 'cost_price_at_sale' => 400, 'line_total' => 960,
        ]);

        app(AggregationService::class)->aggregateDate($date);

        $daily = DailySalesAggregate::withoutTenantScope()->where('branch_id', $branch->id)->whereDate('date', $date)->firstOrFail();
        $this->assertEqualsWithDelta(1020.0, (float) $daily->total_sales, 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $daily->total_tax, 0.01);
        $this->assertSame(1, $daily->transaction_count);
        $this->assertEqualsWithDelta(160.0, (float) $daily->gross_profit, 0.01, 'Profit = (480-400) * 2 = 160.');

        $product2 = ProductPerformanceAggregate::withoutTenantScope()
            ->where('branch_id', $branch->id)->where('product_id', $product->id)->whereDate('date', $date)->firstOrFail();
        $this->assertEqualsWithDelta(2.0, (float) $product2->quantity_sold, 0.001);
        $this->assertEqualsWithDelta(960.0, (float) $product2->revenue, 0.01);

        // Re-running for the same date must update in place, not duplicate rows.
        app(AggregationService::class)->aggregateDate($date);

        $this->assertSame(1, DailySalesAggregate::withoutTenantScope()->where('branch_id', $branch->id)->whereDate('date', $date)->count());
        $this->assertSame(1, ProductPerformanceAggregate::withoutTenantScope()->where('branch_id', $branch->id)->where('product_id', $product->id)->whereDate('date', $date)->count());
    }
}
