<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Dashboard\Services\DashboardService;
use Modules\Products\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the dashboard's role-aware scoping from
 * docs/architecture/05-screen-wireframes.md §5.3: an Owner/Manager sees
 * every branch's numbers, a Cashier only ever sees their own branch's.
 */
class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected array $terminals = [];

    protected ?User $saleClerk = null;

    protected function terminalFor(int $businessId, int $branchId): BranchTerminal
    {
        return $this->terminals[$branchId] ??= BranchTerminal::create([
            'business_id' => $businessId, 'branch_id' => $branchId, 'name' => 'Counter 1',
        ]);
    }

    protected function makeSale(int $businessId, int $branchId, Product $product, float $total): void
    {
        $this->saleClerk ??= User::factory()->create(['business_id' => $businessId]);

        $sale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branchId,
            'terminal_id' => $this->terminalFor($businessId, $branchId)->id,
            'cashier_id' => $this->saleClerk->id,
            'invoice_no' => 'INV-'.random_int(100000, 999999), 'subtotal' => $total,
            'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $total,
            'status' => 'completed', 'sale_type' => 'retail', 'completed_at' => now(),
        ]);

        SaleItem::create([
            'business_id' => $businessId, 'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 1, 'unit_price' => $total, 'cost_price_at_sale' => $total * 0.8, 'line_total' => $total,
        ]);
    }

    public function test_owner_sees_todays_sales_across_all_branches(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        $this->makeSale($businessId, $branchA->id, $product, 500);
        $this->makeSale($businessId, $branchB->id, $product, 700);

        $owner = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branchA->id]);
        $role = Role::create(['name' => 'Owner', 'guard_name' => 'web', 'business_id' => null]);
        $owner->assignRole($role);

        $summary = app(DashboardService::class)->summaryFor($owner);

        $this->assertSame('business', $summary['scope']);
        $this->assertEqualsWithDelta(1200.0, $summary['todays_sales'], 0.01);
        $this->assertSame(2, $summary['transaction_count']);
    }

    public function test_cashier_only_sees_their_own_branchs_sales(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        $this->makeSale($businessId, $branchA->id, $product, 500);
        $this->makeSale($businessId, $branchB->id, $product, 700);

        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branchA->id]);
        $role = Role::create(['name' => 'Cashier', 'guard_name' => 'web', 'business_id' => null]);
        $cashier->assignRole($role);

        $summary = app(DashboardService::class)->summaryFor($cashier);

        $this->assertSame('branch', $summary['scope']);
        $this->assertEqualsWithDelta(500.0, $summary['todays_sales'], 0.01, 'Cashier must only see their own default branch\'s sales, not branch B\'s.');
        $this->assertSame(1, $summary['transaction_count']);
    }
}
