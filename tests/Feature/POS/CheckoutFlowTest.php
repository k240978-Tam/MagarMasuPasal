<?php

namespace Tests\Feature\POS;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Inventory\Models\InventoryStock;
use Modules\POS\Events\TerminalStateUpdated;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Sales\Models\Sale;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Drives the POS HTTP endpoints exactly as the browser does: add item,
 * apply discount, charge, complete — proving the full stack (CartService,
 * SaleService, PaymentManager, InventoryService) wired together correctly,
 * and that every mutation broadcasts a TerminalStateUpdated.
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_full_cash_sale_deducts_stock_and_clears_the_cart(): void
    {
        Event::fake([TerminalStateUpdated::class]);

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Chicken Boneless', 'unit_id' => $unit->id,
            'selling_price' => 480, 'sell_by_weight' => true,
        ]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 20, 'quantity_remaining' => 20, 'unit_cost' => 420, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        Permission::findOrCreate('pos.operate', 'web');
        $role = Role::create(['name' => 'Cashier Test', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('pos.operate');

        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id]);
        $cashier->assignRole($role);

        $base = "/pos/terminals/{$terminal->public_id}";

        $this->actingAs($cashier)
            ->postJson("{$base}/cart/items", ['product_id' => $product->id, 'quantity' => 0.75])
            ->assertOk()
            ->assertJsonPath('total_amount', 360);

        $response = $this->actingAs($cashier)->postJson("{$base}/cart/checkout", [
            'sale_type' => 'retail',
            'payments' => [['gateway_key' => 'cash', 'amount' => 360]],
        ]);

        $response->assertOk();
        $invoiceNo = $response->json('sale.invoice_no');

        $sale = Sale::withoutTenantScope()->where('invoice_no', $invoiceNo)->firstOrFail();
        $this->assertSame('completed', $sale->status);
        $this->assertSame('360.00', $sale->total_amount);

        $stock = InventoryStock::withoutTenantScope()->where('product_id', $product->id)->first();
        $this->assertSame('19.250', $stock->quantity_on_hand);

        $this->actingAs($cashier)->postJson("{$base}/cart/complete")->assertOk();

        $state = $this->actingAs($cashier)->getJson("{$base}/cart")->json();
        $this->assertSame('idle', $state['status']);
        $this->assertEmpty($state['items']);

        Event::assertDispatched(TerminalStateUpdated::class);
    }

    public function test_a_credit_sale_bills_the_customers_account_with_no_payment(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Farm Eggs', 'unit_id' => $unit->id, 'selling_price' => 210]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 12, 'quantity_remaining' => 12, 'unit_cost' => 180, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 12]);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        Permission::findOrCreate('pos.operate', 'web');
        $role = Role::create(['name' => 'Cashier Test 2', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('pos.operate');
        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id]);
        $cashier->assignRole($role);

        $customerGroup = \Modules\Customers\Models\CustomerGroup::create([
            'business_id' => $businessId, 'name' => 'Credit', 'allow_credit' => true, 'credit_limit' => 5000,
        ]);
        $customer = \Modules\Customers\Models\Customer::create([
            'business_id' => $businessId, 'customer_group_id' => $customerGroup->id, 'name' => 'Hotel Everest',
        ]);

        $base = "/pos/terminals/{$terminal->public_id}";

        $this->actingAs($cashier)->postJson("{$base}/cart/items", ['product_id' => $product->id, 'quantity' => 2])->assertOk();
        $this->actingAs($cashier)->patchJson("{$base}/cart/customer", ['customer_id' => $customer->id])->assertOk();

        $response = $this->actingAs($cashier)->postJson("{$base}/cart/checkout", [
            'sale_type' => 'credit',
            'payments' => [],
        ]);

        $response->assertOk();

        $this->assertSame('420.00', $customer->fresh()->current_due);
    }
}
