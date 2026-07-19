<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Models\StockAdjustment;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use RuntimeException;

/**
 * Single write path for stock_movements/inventory_stocks. No other module
 * writes to these tables directly — Sales will call deductFifo() the same
 * way Purchases' listener calls receiveFromBatch(), keeping the ledger the
 * one source of truth for every stock change (docs/architecture/03-database-schema.md §3.3).
 */
class InventoryService
{
    public function receiveFromBatch(PurchaseBatch $batch): StockMovement
    {
        return DB::transaction(function () use ($batch) {
            $movement = StockMovement::create([
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'product_id' => $batch->product_id,
                'batch_id' => $batch->id,
                'type' => 'purchase_receipt',
                'quantity_change' => $batch->quantity_received,
                'reference_type' => PurchaseBatch::class,
                'reference_id' => $batch->id,
                'unit_cost_at_movement' => $batch->unit_cost,
                'created_by' => Auth::id(),
            ]);

            $this->adjustOnHand($batch->business_id, $batch->branch_id, $batch->product_id, (float) $batch->quantity_received);

            return $movement;
        });
    }

    /**
     * Consumes stock oldest-batch-first for a sale (or any other deduction),
     * writing one stock_movements row per batch touched so cost is traceable.
     *
     * @return StockMovement[]
     */
    public function deductFifo(
        int $businessId,
        int $branchId,
        int $productId,
        float $quantity,
        string $type = 'sale',
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array {
        return DB::transaction(function () use ($businessId, $branchId, $productId, $quantity, $type, $referenceType, $referenceId) {
            $remaining = $quantity;
            $movements = [];

            $batches = PurchaseBatch::withoutTenantScope()
                ->where('business_id', $businessId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->where('quantity_remaining', '>', 0)
                ->orderBy('received_at')
                ->lockForUpdate()
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $consumed = min($remaining, (float) $batch->quantity_remaining);
                $batch->decrement('quantity_remaining', $consumed);

                $movements[] = StockMovement::create([
                    'business_id' => $businessId,
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'batch_id' => $batch->id,
                    'type' => $type,
                    'quantity_change' => -$consumed,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'unit_cost_at_movement' => $batch->unit_cost,
                    'created_by' => Auth::id(),
                ]);

                $remaining -= $consumed;
            }

            if ($remaining > 0) {
                throw new RuntimeException(sprintf(
                    'Insufficient stock for product #%d at branch #%d: short by %s.',
                    $productId,
                    $branchId,
                    number_format($remaining, 3),
                ));
            }

            $this->adjustOnHand($businessId, $branchId, $productId, -$quantity);

            return $movements;
        });
    }

    public function recordAdjustment(StockAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment) {
            foreach ($adjustment->items as $item) {
                StockMovement::create([
                    'business_id' => $adjustment->business_id,
                    'branch_id' => $adjustment->branch_id,
                    'product_id' => $item->product_id,
                    'type' => $adjustment->reason === 'recount' ? 'adjustment' : $adjustment->reason,
                    'quantity_change' => $item->quantity,
                    'reference_type' => StockAdjustment::class,
                    'reference_id' => $adjustment->id,
                    'created_by' => $adjustment->created_by,
                ]);

                $this->adjustOnHand($adjustment->business_id, $adjustment->branch_id, $item->product_id, (float) $item->quantity);
            }
        });
    }

    public function recordTransferReceipt(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                StockMovement::create([
                    'business_id' => $transfer->business_id,
                    'branch_id' => $transfer->from_branch_id,
                    'product_id' => $item->product_id,
                    'type' => 'transfer_out',
                    'quantity_change' => -$item->quantity,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $transfer->created_by,
                ]);
                $this->adjustOnHand($transfer->business_id, $transfer->from_branch_id, $item->product_id, -(float) $item->quantity);

                StockMovement::create([
                    'business_id' => $transfer->business_id,
                    'branch_id' => $transfer->to_branch_id,
                    'product_id' => $item->product_id,
                    'type' => 'transfer_in',
                    'quantity_change' => $item->quantity,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $transfer->received_by ?? $transfer->created_by,
                ]);
                $this->adjustOnHand($transfer->business_id, $transfer->to_branch_id, $item->product_id, (float) $item->quantity);
            }
        });
    }

    protected function adjustOnHand(int $businessId, int $branchId, int $productId, float $delta): InventoryStock
    {
        $stock = InventoryStock::withoutTenantScope()->firstOrCreate(
            ['business_id' => $businessId, 'branch_id' => $branchId, 'product_id' => $productId],
            ['quantity_on_hand' => 0, 'quantity_reserved' => 0],
        );

        $stock->increment('quantity_on_hand', $delta);

        return $stock;
    }

    /**
     * Products at or below their configured minimum stock for the branch,
     * using the branch-level override when set, else the product default.
     */
    public function lowStock(int $businessId, int $branchId)
    {
        return InventoryStock::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->whereHas('product', fn ($q) => $q->whereNotNull('min_stock'))
            ->with('product')
            ->get()
            ->filter(function (InventoryStock $stock) {
                $threshold = $stock->product->branchSettings
                    ->firstWhere('branch_id', $stock->branch_id)
                    ?->min_stock ?? $stock->product->min_stock;

                return $threshold !== null && $stock->quantity_on_hand <= $threshold;
            });
    }
}
