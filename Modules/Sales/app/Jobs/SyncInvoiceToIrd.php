<?php

namespace Modules\Sales\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\CbmsSyncService;

/**
 * Reporting an invoice to IRD must never block or fail the sale at the
 * counter, so the push happens out of band and failures stay recorded on the
 * sale for retry.
 */
class SyncInvoiceToIrd implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $saleId) {}

    public function handle(CbmsSyncService $cbms): void
    {
        $sale = Sale::withoutTenantScope()->find($this->saleId);

        if (! $sale) {
            return;
        }

        $cbms->sync($sale);
    }
}
