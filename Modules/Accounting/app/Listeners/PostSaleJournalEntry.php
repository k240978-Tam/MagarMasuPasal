<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Sales\Events\SaleCompleted;
use Modules\Sales\Models\Sale;

/**
 * One balanced entry per sale, covering both sides of a perpetual-inventory
 * sale: revenue recognition (cash/bank/receivable against net revenue) and
 * cost of goods sold (COGS against inventory asset) — see
 * docs/architecture/03-database-schema.md §3.5.
 */
class PostSaleJournalEntry
{
    public function __construct(
        protected JournalEntryService $journal,
        protected ChartOfAccountsService $coa,
    ) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;
        $lines = [];

        foreach ($sale->payments as $payment) {
            $lines[] = [
                'account_code' => $this->coa->accountCodeForGateway($payment->transaction->gateway_key),
                'debit' => (float) $payment->amount,
            ];
        }

        $paid = (float) $sale->payments->sum('amount');
        $due = round((float) $sale->total_amount - $paid, 2);

        if ($due > 0) {
            $lines[] = ['account_code' => ChartOfAccountsService::ACCOUNTS_RECEIVABLE, 'debit' => $due];
        }

        $lines[] = ['account_code' => ChartOfAccountsService::SALES_REVENUE, 'credit' => (float) $sale->total_amount];

        $totalCost = round((float) $sale->items->sum(fn ($item) => $item->cost_price_at_sale * $item->quantity), 2);

        if ($totalCost > 0) {
            $lines[] = ['account_code' => ChartOfAccountsService::COST_OF_GOODS_SOLD, 'debit' => $totalCost];
            $lines[] = ['account_code' => ChartOfAccountsService::INVENTORY_ASSET, 'credit' => $totalCost];
        }

        $this->journal->post(
            businessId: $sale->business_id,
            branchId: $sale->branch_id,
            description: "Sale {$sale->invoice_no}",
            entryDate: $sale->completed_at->toDateString(),
            lines: $lines,
            referenceType: Sale::class,
            referenceId: $sale->id,
            createdBy: $sale->cashier_id,
        );
    }
}
