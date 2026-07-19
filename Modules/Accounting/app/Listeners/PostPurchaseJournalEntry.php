<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Purchases\Events\PurchaseBatchReceived;
use Modules\Purchases\Models\PurchaseBatch;

/**
 * Dr Inventory Asset / Cr Accounts Payable for the batch's cost — the
 * goods-receipt entry. Fires alongside Inventory's own listener on the same
 * event; Accounting never touches stock tables directly.
 */
class PostPurchaseJournalEntry
{
    public function __construct(protected JournalEntryService $journal) {}

    public function handle(PurchaseBatchReceived $event): void
    {
        $batch = $event->batch;
        $amount = round((float) $batch->quantity_received * (float) $batch->unit_cost, 2);

        if ($amount <= 0) {
            return;
        }

        $this->journal->post(
            businessId: $batch->business_id,
            branchId: $batch->branch_id,
            description: "Goods receipt — batch {$batch->batch_number}",
            entryDate: $batch->received_at->toDateString(),
            lines: [
                ['account_code' => ChartOfAccountsService::INVENTORY_ASSET, 'debit' => $amount],
                ['account_code' => ChartOfAccountsService::ACCOUNTS_PAYABLE, 'credit' => $amount],
            ],
            referenceType: PurchaseBatch::class,
            referenceId: $batch->id,
        );
    }
}
