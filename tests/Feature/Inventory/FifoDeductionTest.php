<?php

namespace Tests\Feature\Inventory;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Units\Models\Unit;
use RuntimeException;
use Tests\TestCase;

class FifoDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deduction_consumes_the_oldest_batch_first(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken Boneless', 'unit_id' => $unit->id]);

        $oldBatch = PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => 400,
            'received_at' => now()->subDays(2),
        ]);
        $newBatch = PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => 450,
            'received_at' => now(),
        ]);

        $service = app(InventoryService::class);
        // Seed inventory_stocks to 20 as if both batches had been received through the normal path.
        InventoryStock::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_on_hand' => 20,
        ]);

        // Sell 15 kg — should fully drain the old batch (10) and take 5 from the new one.
        $movements = $service->deductFifo($businessId, $branch->id, $product->id, 15);

        $this->assertCount(2, $movements);
        $this->assertSame('0.000', $oldBatch->fresh()->quantity_remaining);
        $this->assertSame('5.000', $newBatch->fresh()->quantity_remaining);

        // Cost basis on each movement reflects the batch it drew from, not a blended average.
        $this->assertSame('400.00', $movements[0]->unit_cost_at_movement);
        $this->assertSame('450.00', $movements[1]->unit_cost_at_movement);

        $stock = InventoryStock::withoutTenantScope()->where('product_id', $product->id)->first();
        $this->assertSame('5.000', $stock->quantity_on_hand);

        $this->assertSame(2, StockMovement::withoutTenantScope()->where('type', 'sale')->count());
    }

    public function test_deduction_fails_loudly_when_stock_is_insufficient(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Mutton Leg', 'unit_id' => $unit->id]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 3, 'quantity_remaining' => 3, 'unit_cost' => 1050, 'received_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        app(InventoryService::class)->deductFifo($businessId, $branch->id, $product->id, 5);
    }
}
