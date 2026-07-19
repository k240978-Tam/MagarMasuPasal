<?php

namespace Modules\Purchases\Services;

use Illuminate\Support\Facades\DB;
use Modules\Purchases\Events\PurchaseBatchReceived;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Purchases\Models\PurchaseOrder;

class PurchaseService
{
    public function createOrder(array $attributes, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($attributes, $items) {
            $order = PurchaseOrder::create($attributes + ['status' => 'draft']);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order->fresh('items');
        });
    }

    public function markOrdered(PurchaseOrder $order): PurchaseOrder
    {
        $order->update(['status' => 'ordered', 'ordered_at' => now()]);

        return $order;
    }

    /**
     * Receives goods against a PO, in full or in part. Each line creates one
     * PurchaseBatch (the FIFO cost layer) and fires PurchaseBatchReceived,
     * which Inventory turns into a stock_movements entry.
     *
     * @param  array<int, array{purchase_order_item_id: int, quantity: float, unit_cost: float, batch_number?: string|null, expiry_date?: string|null}>  $lines
     */
    public function receiveGoods(PurchaseOrder $order, array $lines): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $lines) {
            foreach ($lines as $line) {
                $item = $order->items()->findOrFail($line['purchase_order_item_id']);

                $quantity = (float) $line['quantity'];

                $item->increment('received_qty', $quantity);

                $batch = PurchaseBatch::create([
                    'business_id' => $order->business_id,
                    'branch_id' => $order->branch_id,
                    'product_id' => $item->product_id,
                    'purchase_order_item_id' => $item->id,
                    'batch_number' => $line['batch_number'] ?? null,
                    'quantity_received' => $quantity,
                    'quantity_remaining' => $quantity,
                    'unit_cost' => $line['unit_cost'],
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'received_at' => now(),
                ]);

                PurchaseBatchReceived::dispatch($batch);
            }

            $order->update(['status' => $this->resolveStatusAfterReceipt($order->fresh('items'))]);

            return $order->fresh(['items', 'items.product']);
        });
    }

    protected function resolveStatusAfterReceipt(PurchaseOrder $order): string
    {
        $fullyReceived = $order->items->every(fn ($item) => $item->outstandingQty() <= 0);

        if ($fullyReceived) {
            return 'received';
        }

        $anyReceived = $order->items->contains(fn ($item) => $item->received_qty > 0);

        return $anyReceived ? 'partially_received' : $order->status;
    }
}
