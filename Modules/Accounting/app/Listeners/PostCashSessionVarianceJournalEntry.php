<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\CashRegister\Events\CashSessionClosed;
use Modules\CashRegister\Models\CashSession;

/**
 * Only posts when the counted drawer doesn't match the expected cash — a
 * short drawer debits Cash Over/Short and credits Cash (the till has less
 * than the books say); an over drawer does the reverse.
 */
class PostCashSessionVarianceJournalEntry
{
    public function __construct(protected JournalEntryService $journal) {}

    public function handle(CashSessionClosed $event): void
    {
        $session = $event->session;
        $variance = (float) $session->variance;

        if (abs($variance) < 0.01) {
            return;
        }

        $short = $variance < 0;
        $amount = abs($variance);

        $this->journal->post(
            businessId: $session->business_id,
            branchId: $session->branch_id,
            description: 'Cash session '.($short ? 'shortage' : 'overage').' — session #'.$session->id,
            entryDate: $session->closed_at->toDateString(),
            lines: $short
                ? [
                    ['account_code' => ChartOfAccountsService::CASH_OVER_SHORT, 'debit' => $amount],
                    ['account_code' => ChartOfAccountsService::CASH, 'credit' => $amount],
                ]
                : [
                    ['account_code' => ChartOfAccountsService::CASH, 'debit' => $amount],
                    ['account_code' => ChartOfAccountsService::CASH_OVER_SHORT, 'credit' => $amount],
                ],
            referenceType: CashSession::class,
            referenceId: $session->id,
            createdBy: $session->closed_by,
        );
    }
}
