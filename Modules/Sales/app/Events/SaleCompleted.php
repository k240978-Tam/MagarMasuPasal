<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Sales\Models\Sale;

/**
 * Fired once a sale is fully finalized (stock deducted, payment captured).
 * Accounting posts the revenue/COGS journal entry from this in a later
 * phase; Notification and low-stock listeners hang off it too — Sales
 * itself never imports either module.
 */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(public Sale $sale) {}
}
