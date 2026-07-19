<?php

namespace Modules\Inventory\Listeners;

use Modules\Inventory\Services\InventoryService;
use Modules\Purchases\Events\PurchaseBatchReceived;

class RecordPurchaseReceipt
{
    public function __construct(protected InventoryService $inventory) {}

    public function handle(PurchaseBatchReceived $event): void
    {
        $this->inventory->receiveFromBatch($event->batch);
    }
}
