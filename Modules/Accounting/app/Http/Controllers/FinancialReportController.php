<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Nepali\NepaliDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\AccountingReportService;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Support\ReportPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(protected AccountingReportService $reports) {}

    public function profitLoss(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('accounting::reports.profit-loss', [
            'report' => $this->profitLossData($request, $period),
            'period' => $period,
        ] + $this->pickerData());
    }

    public function trialBalance(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);
        $rows = $this->reports->trialBalance($request->user()->business_id, $period->asOf()->toDateString());

        return view('accounting::reports.trial-balance', [
            'rows' => $rows,
            'totalDebit' => round($rows->sum('debit'), 2),
            'totalCredit' => round($rows->sum('credit'), 2),
            'period' => $period,
        ] + $this->pickerData());
    }

    public function balanceSheet(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('accounting::reports.balance-sheet', [
            'report' => $this->reports->balanceSheet($request->user()->business_id, $period->asOf()->toDateString()),
            'period' => $period,
        ] + $this->pickerData());
    }

    public function cashBook(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);

        return view('accounting::reports.cash-book', [
            'lines' => $this->cashBookData($request, $period),
            'period' => $period,
        ] + $this->pickerData());
    }

    public function ledger(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);
        [$accounts, $account, $lines] = $this->ledgerData($request, $period);

        return view('accounting::reports.ledger', [
            'accounts' => $accounts,
            'account' => $account,
            'lines' => $lines,
            'period' => $period,
        ] + $this->pickerData());
    }

    /**
     * One CSV endpoint per report, so an accountant can pull the same figures
     * shown on screen into Excel for filing.
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        $period = ReportPeriod::fromRequest($request);

        [$headers, $rows, $name] = match ($report) {
            'profit-loss' => $this->profitLossCsv($request, $period),
            'trial-balance' => $this->trialBalanceCsv($request, $period),
            'balance-sheet' => $this->balanceSheetCsv($request, $period),
            'cash-book' => $this->cashBookCsv($request, $period),
            'ledger' => $this->ledgerCsv($request, $period),
            default => abort(404),
        };

        return $this->streamCsv($headers, $rows, "{$name}-{$period->slug()}.csv", $period);
    }

    /**
     * @return array{report: array<string, mixed>}
     */
    protected function profitLossData(Request $request, ReportPeriod $period): array
    {
        return $this->reports->profitAndLoss(
            $request->user()->business_id,
            $period->from->toDateString(),
            $period->to->toDateString(),
        );
    }

    protected function cashBookData(Request $request, ReportPeriod $period): Collection
    {
        return $this->reports->cashBook(
            $request->user()->business_id,
            $period->from->toDateString(),
            $period->to->toDateString(),
        );
    }

    /**
     * @return array{0: Collection, 1: ChartOfAccount|null, 2: Collection}
     */
    protected function ledgerData(Request $request, ReportPeriod $period): array
    {
        $businessId = $request->user()->business_id;

        $accounts = ChartOfAccount::withoutTenantScope()
            ->where('business_id', $businessId)
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $code = (string) $request->query('account', ChartOfAccountsService::CASH);
        $account = $accounts->firstWhere('code', $code) ?? $accounts->first();

        $lines = $account
            ? $this->reports->generalLedger($businessId, $account->code, $period->from->toDateString(), $period->to->toDateString())
            : collect();

        return [$accounts, $account, $lines];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    protected function profitLossCsv(Request $request, ReportPeriod $period): array
    {
        $report = $this->profitLossData($request, $period);

        $rows = $report['lines']->map(fn ($line) => [
            $line['code'],
            $line['name'],
            ucfirst($line['type']),
            number_format($line['balance'], 2, '.', ''),
        ])->all();

        $rows[] = [];
        $rows[] = ['', 'Total Income', '', number_format($report['income'], 2, '.', '')];
        $rows[] = ['', 'Total Expenses', '', number_format($report['expense'], 2, '.', '')];
        $rows[] = ['', 'Net '.($report['net_profit'] >= 0 ? 'Profit' : 'Loss'), '', number_format(abs($report['net_profit']), 2, '.', '')];

        return [['Account Code', 'Account', 'Type', 'Amount (Rs)'], $rows, 'profit-loss'];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    protected function trialBalanceCsv(Request $request, ReportPeriod $period): array
    {
        $data = $this->reports->trialBalance($request->user()->business_id, $period->asOf()->toDateString());

        $rows = $data->map(fn ($row) => [
            $row['code'],
            $row['name'],
            ucfirst($row['type']),
            number_format($row['debit'], 2, '.', ''),
            number_format($row['credit'], 2, '.', ''),
        ])->all();

        $rows[] = [];
        $rows[] = ['', 'Totals', '', number_format($data->sum('debit'), 2, '.', ''), number_format($data->sum('credit'), 2, '.', '')];

        return [['Account Code', 'Account', 'Type', 'Debit (Rs)', 'Credit (Rs)'], $rows, 'trial-balance'];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    protected function balanceSheetCsv(Request $request, ReportPeriod $period): array
    {
        $report = $this->reports->balanceSheet($request->user()->business_id, $period->asOf()->toDateString());

        $rows = $report['lines']->map(fn ($line) => [
            $line['code'],
            $line['name'],
            ucfirst($line['type']),
            number_format($line['balance'], 2, '.', ''),
        ])->all();

        $rows[] = ['', 'Retained Earnings', 'Equity', number_format($report['retained_earnings'], 2, '.', '')];
        $rows[] = [];
        $rows[] = ['', 'Total Assets', '', number_format($report['assets'], 2, '.', '')];
        $rows[] = ['', 'Total Liabilities', '', number_format($report['liabilities'], 2, '.', '')];
        $rows[] = ['', 'Total Equity', '', number_format($report['equity'] + $report['retained_earnings'], 2, '.', '')];

        return [['Account Code', 'Account', 'Type', 'Balance (Rs)'], $rows, 'balance-sheet'];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    protected function cashBookCsv(Request $request, ReportPeriod $period): array
    {
        $rows = $this->cashBookData($request, $period)->map(fn ($line) => [
            NepaliDate::format($line->entry_date),
            Carbon::parse($line->entry_date)->toDateString(),
            $line->description,
            number_format((float) $line->debit, 2, '.', ''),
            number_format((float) $line->credit, 2, '.', ''),
            number_format($line->running_balance, 2, '.', ''),
        ])->all();

        return [['Date (BS)', 'Date (AD)', 'Description', 'Cash In (Rs)', 'Cash Out (Rs)', 'Balance (Rs)'], $rows, 'cash-book'];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>, 2: string}
     */
    protected function ledgerCsv(Request $request, ReportPeriod $period): array
    {
        [, $account, $lines] = $this->ledgerData($request, $period);

        $rows = $lines->map(fn ($line) => [
            NepaliDate::format($line->entry_date),
            Carbon::parse($line->entry_date)->toDateString(),
            $line->description,
            number_format((float) $line->debit, 2, '.', ''),
            number_format((float) $line->credit, 2, '.', ''),
            number_format($line->running_balance, 2, '.', ''),
        ])->all();

        $name = 'ledger-'.($account?->code ?? 'account');

        return [['Date (BS)', 'Date (AD)', 'Description', 'Debit (Rs)', 'Credit (Rs)', 'Balance (Rs)'], $rows, $name];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function streamCsv(array $headers, array $rows, string $filename, ReportPeriod $period): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $period) {
            $handle = fopen('php://output', 'wb');

            // Period is stamped into the file itself: a bare CSV of figures
            // is useless to an accountant who can't tell which month it is.
            fputcsv($handle, ['Period', $period->label]);
            fputcsv($handle, ['Range (BS)', $period->fromBs().' to '.$period->toBs()]);
            fputcsv($handle, ['Range (AD)', $period->from->toDateString().' to '.$period->to->toDateString()]);
            fputcsv($handle, []);
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{nepaliMonths: array<int, array{value: string, label: string}>}
     */
    protected function pickerData(): array
    {
        return ['nepaliMonths' => NepaliDate::recentMonths(24)];
    }
}
