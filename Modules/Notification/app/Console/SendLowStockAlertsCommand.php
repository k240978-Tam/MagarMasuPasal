<?php

namespace Modules\Notification\Console;

use Illuminate\Console\Command;
use Modules\Inventory\Services\InventoryService;
use Modules\Notification\Notifications\LowStockAlert;
use Modules\Notification\Services\NotificationDispatchService;
use Modules\Tenancy\Models\Branch;

class SendLowStockAlertsCommand extends Command
{
    protected $signature = 'notifications:low-stock';

    protected $description = 'Notify each business\'s management of products at or below their reorder threshold, one digest per branch.';

    public function handle(InventoryService $inventory, NotificationDispatchService $dispatch): int
    {
        Branch::withoutTenantScope()->where('status', 'active')->each(function (Branch $branch) use ($inventory, $dispatch) {
            $lowStock = $inventory->lowStock($branch->business_id, $branch->id);

            if ($lowStock->isEmpty()) {
                return;
            }

            $items = $lowStock->map(fn ($stock) => [
                'product' => $stock->product->name,
                'branch' => $branch->name,
                'on_hand' => (float) $stock->quantity_on_hand,
                'threshold' => (float) ($stock->product->branchSettings->firstWhere('branch_id', $branch->id)?->min_stock ?? $stock->product->min_stock),
            ])->values()->all();

            $dispatch->notifyManagement($branch->business_id, new LowStockAlert($branch->name, $items));
        });

        $this->info('Low stock alerts dispatched.');

        return self::SUCCESS;
    }
}
