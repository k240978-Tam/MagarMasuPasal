<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Expenses\Events\ExpenseRecorded;
use Modules\Expenses\Models\Expense;

class PostExpenseJournalEntry
{
    public function __construct(protected JournalEntryService $journal) {}

    public function handle(ExpenseRecorded $event): void
    {
        $expense = $event->expense;
        $amount = (float) $expense->amount;

        $expenseAccountCode = $expense->category?->chartOfAccount?->code;

        $paidAccountCode = $expense->paid_via === 'bank'
            ? ChartOfAccountsService::BANK
            : ChartOfAccountsService::CASH;

        $this->journal->post(
            businessId: $expense->business_id,
            branchId: $expense->branch_id,
            description: "Expense: {$expense->category?->name} ({$expense->vendor})",
            entryDate: $expense->expense_date->toDateString(),
            lines: [
                ['account_code' => $expenseAccountCode ?? ChartOfAccountsService::GENERAL_EXPENSES, 'debit' => $amount],
                ['account_code' => $paidAccountCode, 'credit' => $amount],
            ],
            referenceType: Expense::class,
            referenceId: $expense->id,
            createdBy: $expense->created_by,
        );
    }
}
