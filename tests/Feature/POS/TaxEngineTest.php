<?php

namespace Tests\Feature\POS;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Inventory\Models\InventoryStock;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Sales\Models\Sale;
use Modules\Settings\Models\TaxRule;
use Modules\Settings\Services\TaxCalculationService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the tax rule engine's core invariant: a product is only taxed when
 * it explicitly references an active TaxRule. This keeps every business
 * that hasn't configured tax rules — including the existing CheckoutFlowTest
 * fixtures — completely unaffected (0 tax everywhere), while letting an
 * Owner opt specific goods (e.g. packaged snacks, not fresh meat) into VAT.
 */
class TaxEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_for_rule_is_zero_when_product_has_no_tax_rule(): void
    {
        $branch = BranchFactory::new()->create();

        $rate = app(TaxCalculationService::class)->rateForRule($branch->business_id, null);

        $this->assertSame(0.0, $rate);
    }

    public function test_rate_for_rule_ignores_an_inactive_rule(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;

        $rule = TaxRule::create(['business_id' => $businessId, 'name' => 'VAT', 'rate' => 13, 'is_active' => false]);

        $rate = app(TaxCalculationService::class)->rateForRule($businessId, $rule->id);

        $this->assertSame(0.0, $rate);
    }

    public function test_a_sale_of_a_taxed_product_computes_correct_tax_and_total(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $taxRule = TaxRule::create(['business_id' => $businessId, 'name' => 'VAT', 'rate' => 13, 'is_active' => true]);

        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'conversion_factor' => 1]);
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Packaged Spice Mix', 'unit_id' => $unit->id,
            'selling_price' => 100, 'tax_rule_id' => $taxRule->id,
        ]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => 70, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        Permission::findOrCreate('pos.operate', 'web');
        $role = Role::create(['name' => 'Tax Test Cashier', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('pos.operate');
        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id]);
        $cashier->assignRole($role);

        $base = "/pos/terminals/{$terminal->public_id}";

        $addResponse = $this->actingAs($cashier)
            ->postJson("{$base}/cart/items", ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        // 2 * 100 = 200 subtotal, 13% VAT = 26 tax, total 226.
        $addResponse->assertJsonPath('tax_amount', 26)
            ->assertJsonPath('total_amount', 226);

        $checkout = $this->actingAs($cashier)->postJson("{$base}/cart/checkout", [
            'sale_type' => 'retail',
            'payments' => [['gateway_key' => 'cash', 'amount' => 226]],
        ])->assertOk();

        $sale = Sale::withoutTenantScope()->where('invoice_no', $checkout->json('sale.invoice_no'))->firstOrFail();
        $this->assertSame('26.00', $sale->tax_amount);
        $this->assertSame('226.00', $sale->total_amount);
    }

    public function test_a_sale_of_an_untagged_product_stays_tax_exempt(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        // A tax rule exists for the business, but this product never references it.
        TaxRule::create(['business_id' => $businessId, 'name' => 'VAT', 'rate' => 13, 'is_active' => true]);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Fresh Goat Meat', 'unit_id' => $unit->id, 'selling_price' => 900]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => 750, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        Permission::findOrCreate('pos.operate', 'web');
        $role = Role::create(['name' => 'Tax Test Cashier 2', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('pos.operate');
        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id]);
        $cashier->assignRole($role);

        $base = "/pos/terminals/{$terminal->public_id}";

        $this->actingAs($cashier)
            ->postJson("{$base}/cart/items", ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('tax_amount', 0)
            ->assertJsonPath('total_amount', 900);
    }
}
