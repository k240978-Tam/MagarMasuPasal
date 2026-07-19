<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Accounting\Models\JournalEntry;

/**
 * Single write path for journal_entries/journal_entry_lines. Every listener
 * that auto-posts from a domain event (SaleCompleted, PurchaseBatchReceived,
 * ExpenseRecorded, ...) goes through here, so "every entry balances" is
 * enforced in exactly one place rather than trusted to each caller.
 */
class JournalEntryService
{
    /**
     * @param  array<int, array{account_code: string, debit?: float, credit?: float}>  $lines
     */
    public function post(
        int $businessId,
        ?int $branchId,
        string $description,
        string $entryDate,
        array $lines,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
    ): JournalEntry {
        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(
                "Journal entry does not balance: debits {$totalDebit} vs credits {$totalCredit} ({$description})."
            );
        }

        return DB::transaction(function () use ($businessId, $branchId, $description, $entryDate, $lines, $referenceType, $referenceId, $createdBy) {
            $coa = app(ChartOfAccountsService::class);

            $entry = JournalEntry::create([
                'business_id' => $businessId,
                'branch_id' => $branchId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'entry_date' => $entryDate,
                'created_by' => $createdBy,
                'status' => 'posted',
            ]);

            foreach ($lines as $line) {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit === 0.0 && $credit === 0.0) {
                    continue;
                }

                $entry->lines()->create([
                    'business_id' => $businessId,
                    'account_id' => $coa->find($businessId, $line['account_code'])->id,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            return $entry->load('lines.account');
        });
    }
}
