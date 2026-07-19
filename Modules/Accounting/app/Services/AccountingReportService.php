<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryLine;

/**
 * Read-side of Accounting: every report here is derived purely from
 * journal_entry_lines, never from Sales/Purchases/Expenses tables directly —
 * the ledger is the single source of truth once an entry is posted.
 */
class AccountingReportService
{
    protected const DEBIT_NORMAL = ['asset', 'expense'];

    /**
     * Ledger for one account, in date order, with a running balance in the
     * account's natural direction (debit-normal accounts increase on debit).
     */
    public function generalLedger(int $businessId, string $accountCode, ?string $from = null, ?string $to = null): Collection
    {
        $account = app(ChartOfAccountsService::class)->find($businessId, $accountCode);

        $lines = JournalEntryLine::withoutTenantScope()
            ->where('journal_entry_lines.business_id', $businessId)
            ->where('account_id', $account->id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $to))
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entry_lines.id')
            ->get([
                'journal_entry_lines.*',
                'journal_entries.entry_date',
                'journal_entries.description',
            ]);

        $debitNormal = in_array($account->type, self::DEBIT_NORMAL, true);
        $running = 0.0;

        return $lines->map(function ($line) use (&$running, $debitNormal) {
            $running += $debitNormal
                ? ((float) $line->debit - (float) $line->credit)
                : ((float) $line->credit - (float) $line->debit);

            $line->running_balance = round($running, 2);

            return $line;
        });
    }

    /**
     * One row per account with its natural-direction balance; total debit
     * column must equal total credit column when the ledger is healthy.
     */
    public function trialBalance(int $businessId, ?string $asOf = null): Collection
    {
        $accounts = ChartOfAccount::withoutTenantScope()->where('business_id', $businessId)->orderBy('code')->get();

        $totals = JournalEntryLine::withoutTenantScope()
            ->where('journal_entry_lines.business_id', $businessId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->when($asOf, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $asOf))
            ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function (ChartOfAccount $account) use ($totals) {
            $row = $totals->get($account->id);
            $debit = (float) ($row->total_debit ?? 0);
            $credit = (float) ($row->total_credit ?? 0);
            // Net debit-credit determines the column, independent of the
            // account's "normal" side — an overdrawn cash account (a
            // debit-normal account sitting in a net credit position) must
            // still show up on the credit side, or the trial balance won't
            // balance at all.
            $net = round($debit - $credit, 2);

            return [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $net > 0 ? $net : 0.0,
                'credit' => $net < 0 ? -$net : 0.0,
            ];
        })->filter(fn ($row) => $row['debit'] != 0 || $row['credit'] != 0)->values();
    }

    /**
     * @return array{income: float, expense: float, net_profit: float, lines: Collection}
     */
    public function profitAndLoss(int $businessId, string $from, string $to): array
    {
        $totals = $this->accountTypeTotals($businessId, $from, $to, ['income', 'expense']);

        $income = $totals->where('type', 'income')->sum('balance');
        $expense = $totals->where('type', 'expense')->sum('balance');

        return [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net_profit' => round($income - $expense, 2),
            'lines' => $totals,
        ];
    }

    /**
     * @return array{assets: float, liabilities: float, equity: float, retained_earnings: float, lines: Collection}
     */
    public function balanceSheet(int $businessId, string $asOf): array
    {
        $totals = $this->accountTypeTotals($businessId, null, $asOf, ['asset', 'liability', 'equity']);

        // Retained earnings = all-time net profit not yet in the ledger as
        // an equity account — computed from income/expense so the sheet
        // balances without a manual year-end closing entry.
        $pnl = $this->profitAndLoss($businessId, '0001-01-01', $asOf);

        return [
            'assets' => round($totals->where('type', 'asset')->sum('balance'), 2),
            'liabilities' => round($totals->where('type', 'liability')->sum('balance'), 2),
            'equity' => round($totals->where('type', 'equity')->sum('balance'), 2),
            'retained_earnings' => $pnl['net_profit'],
            'lines' => $totals,
        ];
    }

    public function cashBook(int $businessId, ?string $from = null, ?string $to = null): Collection
    {
        return $this->generalLedger($businessId, ChartOfAccountsService::CASH, $from, $to);
    }

    protected function accountTypeTotals(int $businessId, ?string $from, ?string $to, array $types): Collection
    {
        $accounts = ChartOfAccount::withoutTenantScope()
            ->where('business_id', $businessId)
            ->whereIn('type', $types)
            ->get();

        $totals = JournalEntryLine::withoutTenantScope()
            ->where('journal_entry_lines.business_id', $businessId)
            ->whereIn('account_id', $accounts->pluck('id'))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.entry_date', '<=', $to))
            ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        return $accounts->map(function (ChartOfAccount $account) use ($totals) {
            $row = $totals->get($account->id);
            $debit = (float) ($row->total_debit ?? 0);
            $credit = (float) ($row->total_credit ?? 0);
            $debitNormal = in_array($account->type, self::DEBIT_NORMAL, true);

            return [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => round($debitNormal ? $debit - $credit : $credit - $debit, 2),
            ];
        });
    }
}
