<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Services\StockTransferService;
use Modules\POS\Models\HeldBill;
use Modules\Products\Models\Product;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Insecure Direct Object Reference checks: a user authenticated to business
 * A must never reach business B's records just by knowing (or guessing)
 * their public_id, even though every route uses route-model binding that
 * would otherwise happily resolve them. Complements the tenant-isolation
 * sweep (which checks queries) with checks on write endpoints specifically,
 * per the roadmap's "penetration-test pass on ... auth flows" item.
 */
class IdorTest extends TestCase
{
    use RefreshDatabase;

    protected function actor(int $businessId, array $permissions = []): User
    {
        $user = User::factory()->create(['business_id' => $businessId]);

        if ($permissions) {
            $role = Role::create(['name' => 'IDOR Test '.uniqid(), 'guard_name' => 'web', 'business_id' => null]);
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
            $role->givePermissionTo($permissions);
            $user->assignRole($role);
        }

        return $user;
    }

    public function test_a_user_cannot_open_another_businesss_pos_terminal(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        $terminalB = BranchTerminal::create(['business_id' => $branchB->business_id, 'branch_id' => $branchB->id, 'name' => 'Counter 1']);
        $userA = $this->actor($branchA->business_id, ['pos.operate']);

        // 404, not 403: TenantScope filters the cross-tenant terminal out of
        // route-model binding entirely (ResolveTenant runs before
        // SubstituteBindings), so it never reaches the controller's own
        // abort_unless check at all — the more secure outcome, since it
        // doesn't even confirm the terminal exists.
        $this->actingAs($userA)->get("/pos/terminals/{$terminalB->public_id}")->assertNotFound();
        $this->actingAs($userA)->getJson("/pos/terminals/{$terminalB->public_id}/cart")->assertNotFound();
    }

    public function test_a_user_cannot_resume_another_businesss_held_bill(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        $terminalA = BranchTerminal::create(['business_id' => $branchA->business_id, 'branch_id' => $branchA->id, 'name' => 'Counter 1']);
        $terminalB = BranchTerminal::create(['business_id' => $branchB->business_id, 'branch_id' => $branchB->id, 'name' => 'Counter 1']);

        $heldBillB = HeldBill::create([
            'business_id' => $branchB->business_id, 'branch_id' => $branchB->id, 'terminal_id' => $terminalB->id,
            'cashier_id' => $this->actor($branchB->business_id)->id, 'cart_snapshot' => ['items' => []], 'held_at' => now(),
        ]);

        $userA = $this->actor($branchA->business_id, ['pos.operate']);

        $this->actingAs($userA)
            ->postJson("/pos/terminals/{$terminalA->public_id}/cart/resume/{$heldBillB->public_id}")
            ->assertNotFound();
    }

    public function test_a_user_cannot_receive_or_cancel_another_businesss_stock_transfer(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchA2 = BranchFactory::new()->create(['business_id' => $branchA->business_id]);
        $branchB = BranchFactory::new()->create();

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $branchA->business_id, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        InventoryStock::create(['business_id' => $branchA->business_id, 'branch_id' => $branchA->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);

        $userA = $this->actor($branchA->business_id);
        $transfer = app(StockTransferService::class)->initiate(
            $branchA->business_id, $branchA->id, $branchA2->id,
            [['product_id' => $product->id, 'quantity' => 2]],
            $userA->id,
        );

        $userB = $this->actor($branchB->business_id, ['inventory.manage']);

        $this->actingAs($userB)->post("/inventory/transfers/{$transfer->public_id}/receive")->assertNotFound();
        $this->actingAs($userB)->post("/inventory/transfers/{$transfer->public_id}/cancel")->assertNotFound();

        $this->assertSame('in_transit', $transfer->fresh()->status, "Business B's forbidden request must not have mutated business A's transfer.");
    }
}
