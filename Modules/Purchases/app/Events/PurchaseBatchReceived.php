<?php

namespace Modules\Purchases\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Purchases\Models\PurchaseBatch;

/**
 * Fired once per batch created on goods receipt. Inventory listens to this
 * to write the stock_movements ledger entry and update inventory_stocks —
 * Purchases never touches inventory tables directly, keeping the dependency
 * one-directional per docs/architecture/02-module-breakdown.md.
 */
class PurchaseBatchReceived
{
    use Dispatchable;

    public function __construct(public PurchaseBatch $batch) {}
}
