<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Services\StockTransferService;
use Modules\Products\Models\Product;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Units\Models\Unit;
use RuntimeException;
use Tests\TestCase;

/**
 * Proves the dispatch/receive workflow: nothing moves at dispatch time
 * (branch A keeps its full on-hand quantity while a transfer is in transit),
 * and receiving atomically debits the source and credits the destination —
 * the exact behavior InventoryService::recordTransferReceipt was built for.
 */
class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatching_does_not_move_stock_until_received(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branchA->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $user = User::factory()->create(['business_id' => $businessId]);

        $transfer = app(StockTransferService::class)->initiate(
            $businessId, $branchA->id, $branchB->id,
            [['product_id' => $product->id, 'quantity' => 5]],
            $user->id,
        );

        $this->assertSame('in_transit', $transfer->status);

        $stockA = InventoryStock::withoutTenantScope()->where('branch_id', $branchA->id)->where('product_id', $product->id)->first();
        $this->assertSame('20.000', $stockA->quantity_on_hand, 'Stock must not move until the transfer is received.');

        $stockB = InventoryStock::withoutTenantScope()->where('branch_id', $branchB->id)->where('product_id', $product->id)->first();
        $this->assertNull($stockB);

        app(StockTransferService::class)->receive($transfer, $user->id);

        $this->assertSame('15.000', $stockA->fresh()->quantity_on_hand);
        $this->assertSame('5.000', InventoryStock::withoutTenantScope()->where('branch_id', $branchB->id)->where('product_id', $product->id)->value('quantity_on_hand'));
        $this->assertSame('received', $transfer->fresh()->status);
    }

    public function test_initiating_a_transfer_beyond_available_stock_fails_loudly(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branchA->id, 'product_id' => $product->id, 'quantity_on_hand' => 3]);

        $user = User::factory()->create(['business_id' => $businessId]);

        $this->expectException(RuntimeException::class);

        app(StockTransferService::class)->initiate(
            $businessId, $branchA->id, $branchB->id,
            [['product_id' => $product->id, 'quantity' => 10]],
            $user->id,
        );
    }

    public function test_a_cancelled_transfer_never_touches_stock(): void
    {
        $branchA = BranchFactory::new()->create();
        $businessId = $branchA->business_id;
        $branchB = BranchFactory::new()->create(['business_id' => $businessId]);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branchA->id, 'product_id' => $product->id, 'quantity_on_hand' => 20]);

        $user = User::factory()->create(['business_id' => $businessId]);
        $transfers = app(StockTransferService::class);

        $transfer = $transfers->initiate($businessId, $branchA->id, $branchB->id, [['product_id' => $product->id, 'quantity' => 5]], $user->id);
        $transfers->cancel($transfer);

        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame('20.000', InventoryStock::withoutTenantScope()->where('branch_id', $branchA->id)->where('product_id', $product->id)->value('quantity_on_hand'));

        $this->expectException(RuntimeException::class);
        $transfers->receive($transfer, $user->id);
    }
}
