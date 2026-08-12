<?php

namespace Modules\Sales\Listeners;

use Modules\Sales\Events\SaleCompleted;
use Modules\Sales\Jobs\SyncInvoiceToIrd;
use Modules\Sales\Services\CbmsSyncService;

/**
 * Hands each completed invoice to IRD's CBMS. Skipped entirely when CBMS is
 * not configured, so a shop that isn't (yet) on real-time reporting doesn't
 * accumulate a queue of jobs that can only fail.
 */
class QueueIrdInvoiceSync
{
    public function __construct(protected CbmsSyncService $cbms) {}

    public function handle(SaleCompleted $event): void
    {
        if (! $this->cbms->enabled()) {
            $event->sale->forceFill(['ird_sync_status' => 'disabled'])->save();

            return;
        }

        SyncInvoiceToIrd::dispatch($event->sale->id);
    }
}
