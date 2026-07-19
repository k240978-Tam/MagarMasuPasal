<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Inventory\Models\InventoryStock;
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
 * The mobile-client finalize-sale endpoint: proves prices/tax are always
 * recomputed server-side (never trusted from the request), that a retried
 * request with the same Idempotency-Key returns the original sale instead
 * of creating a second one, and that cross-tenant terminal access 404s the
 * same way the web POS does.
 */
class PosSaleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpCashierAndTerminal(): array
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 20, 'quantity_remaining' => 20, 'unit_cost' => 400, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        Permission::findOrCreate('pos.operate', 'web');
        $role = Role::create(['name' => 'API Cashier '.uniqid(), 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('pos.operate');
        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id, 'password' => 'password']);
        $cashier->assignRole($role);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $cashier->email, 'password' => 'password', 'device_name' => 'test-suite',
        ])->json('data.token');

        return compact('terminal', 'product', 'token', 'businessId', 'branch', 'cashier');
    }

    public function test_finalizing_a_sale_recomputes_price_server_side_and_deducts_stock(): void
    {
        ['terminal' => $terminal, 'product' => $product, 'token' => $token] = $this->setUpCashierAndTerminal();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', 'test-key-1')
            ->postJson("/api/v1/pos/terminals/{$terminal->public_id}/sales", [
                'sale_type' => 'retail',
                // A client-supplied unit_price would be ignored even if sent —
                // there's no unit_price field accepted at all, only quantity.
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
                'payments' => [['gateway_key' => 'cash', 'amount' => 960]],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.total_amount', 960);

        $sale = Sale::withoutTenantScope()->where('public_id', $response->json('data.public_id'))->firstOrFail();
        $this->assertSame('960.00', $sale->total_amount);

        $stock = InventoryStock::withoutTenantScope()->where('product_id', $product->id)->first();
        $this->assertSame('18.000', $stock->quantity_on_hand);
    }

    public function test_a_retried_request_with_the_same_idempotency_key_does_not_duplicate_the_sale(): void
    {
        ['terminal' => $terminal, 'product' => $product, 'token' => $token] = $this->setUpCashierAndTerminal();

        $payload = [
            'sale_type' => 'retail',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['gateway_key' => 'cash', 'amount' => 480]],
        ];

        $first = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', 'retry-key-1')
            ->postJson("/api/v1/pos/terminals/{$terminal->public_id}/sales", $payload);

        $second = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', 'retry-key-1')
            ->postJson("/api/v1/pos/terminals/{$terminal->public_id}/sales", $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.public_id'), $second->json('data.public_id'), 'The retried request must return the same sale, not create a new one.');

        $this->assertSame(1, Sale::withoutTenantScope()->count());

        $stock = InventoryStock::withoutTenantScope()->where('product_id', $product->id)->first();
        $this->assertSame('19.000', $stock->quantity_on_hand, 'Stock must only be deducted once, even though the request was sent twice.');
    }

    public function test_a_missing_idempotency_key_is_rejected(): void
    {
        ['terminal' => $terminal, 'product' => $product, 'token' => $token] = $this->setUpCashierAndTerminal();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/pos/terminals/{$terminal->public_id}/sales", [
                'sale_type' => 'retail',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payments' => [['gateway_key' => 'cash', 'amount' => 480]],
            ])
            ->assertStatus(400);
    }

    public function test_a_user_cannot_finalize_a_sale_on_another_businesss_terminal(): void
    {
        ['token' => $token, 'product' => $product] = $this->setUpCashierAndTerminal();

        $otherBranch = BranchFactory::new()->create();
        $otherTerminal = BranchTerminal::create(['business_id' => $otherBranch->business_id, 'branch_id' => $otherBranch->id, 'name' => 'Counter 1']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', 'cross-tenant-key')
            ->postJson("/api/v1/pos/terminals/{$otherTerminal->public_id}/sales", [
                'sale_type' => 'retail',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payments' => [['gateway_key' => 'cash', 'amount' => 480]],
            ])
            ->assertNotFound();
    }
}
