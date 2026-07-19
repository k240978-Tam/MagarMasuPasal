<?php

namespace Tests\Feature\Purchases;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Models\StockMovement;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Purchases\Services\PurchaseService;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Units\Models\Unit;
use Tests\TestCase;

/**
 * Proves the Phase 1 exit criteria from docs/architecture/08-roadmap.md:
 * a purchase order received into stock creates a FIFO batch and updates
 * stock levels with a full movement audit trail — entirely through the
 * PurchaseBatchReceived event, no direct coupling between the two modules.
 */
class GoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_a_purchase_order_creates_a_batch_and_updates_stock(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create([
            'business_id' => $businessId,
            'name' => 'Chicken Boneless',
            'unit_id' => $unit->id,
            'sell_by_weight' => true,
            'track_batches' => true,
        ]);
        $supplier = Supplier::create(['business_id' => $businessId, 'name' => 'Kalimati Traders']);
        $user = User::factory()->create(['business_id' => $businessId]);

        $service = app(PurchaseService::class);

        $order = $service->createOrder(
            [
                'business_id' => $businessId,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'created_by' => $user->id,
            ],
            [['business_id' => $businessId, 'product_id' => $product->id, 'ordered_qty' => 20, 'unit_cost' => 420]],
        );

        $order = $service->receiveGoods($order, [
            [
                'purchase_order_item_id' => $order->items[0]->id,
                'quantity' => 20,
                'unit_cost' => 420,
                'batch_number' => 'B-0001',
            ],
        ]);

        $this->assertSame('received', $order->status);
        $this->assertSame('20.000', PurchaseBatch::withoutTenantScope()->first()->quantity_remaining);

        $movement = StockMovement::withoutTenantScope()->first();
        $this->assertSame('purchase_receipt', $movement->type);
        $this->assertSame('20.000', $movement->quantity_change);

        $stock = InventoryStock::withoutTenantScope()->where('product_id', $product->id)->first();
        $this->assertSame('20.000', $stock->quantity_on_hand);
    }

    public function test_partial_receipt_leaves_the_order_partially_received(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Mutton Leg', 'unit_id' => $unit->id,
        ]);
        $supplier = Supplier::create(['business_id' => $businessId, 'name' => 'Kalimati Traders']);
        $user = User::factory()->create(['business_id' => $businessId]);

        $service = app(PurchaseService::class);

        $order = $service->createOrder(
            [
                'business_id' => $businessId, 'branch_id' => $branch->id,
                'supplier_id' => $supplier->id, 'created_by' => $user->id,
            ],
            [['business_id' => $businessId, 'product_id' => $product->id, 'ordered_qty' => 10, 'unit_cost' => 1050]],
        );

        $order = $service->receiveGoods($order, [
            ['purchase_order_item_id' => $order->items[0]->id, 'quantity' => 4, 'unit_cost' => 1050],
        ]);

        $this->assertSame('partially_received', $order->status);
    }
}
