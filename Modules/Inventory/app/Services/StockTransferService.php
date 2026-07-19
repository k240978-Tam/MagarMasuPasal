<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryStock;
use Modules\Inventory\Models\StockTransfer;
use RuntimeException;

/**
 * Dispatch/receive workflow for moving stock between branches. Nothing
 * touches inventory_stocks at dispatch time — a transfer only becomes a
 * real stock movement (transfer_out at the source, transfer_in at the
 * destination, both via InventoryService::recordTransferReceipt) once the
 * receiving branch confirms it, mirroring the Purchases PO → goods-receipt
 * pattern already established for the same reason: the paper trail and the
 * physical event shouldn't be the same moment.
 */
class StockTransferService
{
    public function __construct(protected InventoryService $inventory) {}

    /**
     * @param  array<int, array{product_id: int, quantity: float}>  $items
     */
    public function initiate(
        int $businessId,
        int $fromBranchId,
        int $toBranchId,
        array $items,
        int $createdBy,
        ?string $notes = null,
    ): StockTransfer {
        if ($fromBranchId === $toBranchId) {
            throw new RuntimeException('Source and destination branch must be different.');
        }

        if (empty($items)) {
            throw new RuntimeException('A transfer needs at least one item.');
        }

        return DB::transaction(function () use ($businessId, $fromBranchId, $toBranchId, $items, $createdBy, $notes) {
            foreach ($items as $item) {
                $available = (float) (InventoryStock::withoutTenantScope()
                    ->where('business_id', $businessId)
                    ->where('branch_id', $fromBranchId)
                    ->where('product_id', $item['product_id'])
                    ->value('quantity_on_hand') ?? 0);

                if ($available < (float) $item['quantity']) {
                    throw new RuntimeException(sprintf(
                        'Insufficient stock at the source branch for product #%d: has %s, requested %s.',
                        $item['product_id'],
                        number_format($available, 3),
                        number_format((float) $item['quantity'], 3),
                    ));
                }
            }

            $transfer = StockTransfer::create([
                'business_id' => $businessId,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => 'in_transit',
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create([
                    'business_id' => $businessId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $transfer->fresh('items.product');
        });
    }

    public function receive(StockTransfer $transfer, int $receivedBy): StockTransfer
    {
        if (! in_array($transfer->status, ['pending', 'in_transit'], true)) {
            throw new RuntimeException('Only a pending or in-transit transfer can be received.');
        }

        return DB::transaction(function () use ($transfer, $receivedBy) {
            $transfer->update(['received_by' => $receivedBy]);

            $this->inventory->recordTransferReceipt($transfer->fresh('items'));

            $transfer->update(['status' => 'received', 'received_at' => now()]);

            return $transfer->fresh('items.product');
        });
    }

    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if (! in_array($transfer->status, ['pending', 'in_transit'], true)) {
            throw new RuntimeException('Only a pending or in-transit transfer can be cancelled.');
        }

        $transfer->update(['status' => 'cancelled']);

        return $transfer;
    }
}
