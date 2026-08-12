<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Nepali\NepaliDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\AccountingReportService;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Support\ReportDocument;
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
     * Every report in every format, from the same rows the screen shows.
     *
     * @return Response|StreamedResponse
     */
    public function export(Request $request, string $report)
    {
        $period = ReportPeriod::fromRequest($request);
        $format = $this->format($request);

        $document = match ($report) {
            'profit-loss' => $this->profitLossDocument($request, $period),
            'trial-balance' => $this->trialBalanceDocument($request, $period),
            'balance-sheet' => $this->balanceSheetDocument($request, $period),
            'cash-book' => $this->cashBookDocument($request, $period),
            'ledger' => $this->ledgerDocument($request, $period),
            default => abort(404),
        };

        return $document->render($format);
    }

    /**
     * The bundle handed to an accountant each month: every statement for one
     * period in a single file, instead of five separate downloads.
     *
     * @return Response|StreamedResponse
     */
    public function monthlyPack(Request $request)
    {
        $period = ReportPeriod::fromRequest($request);

        $document = ReportDocument::make(
            __('reports.monthly_pack'),
            $period,
            'monthly-pack-'.$period->slug(),
        );

        foreach ([
            $this->profitLossDocument($request, $period),
            $this->trialBalanceDocument($request, $period),
            $this->balanceSheetDocument($request, $period),
            $this->cashBookDocument($request, $period),
        ] as $part) {
            foreach ($part->sections() as $section) {
                $document->addSection(
                    $section['title'],
                    $section['headers'],
                    $section['rows'],
                    $section['numericColumns'],
                    $section['totalRows'],
                    $section['summary'],
                    $section['columnWidths'],
                );
            }
        }

        return $document->render($this->format($request));
    }

    protected function format(Request $request): string
    {
        $format = (string) $request->query('format', 'csv');

        return in_array($format, ['csv', 'xlsx', 'excel', 'pdf'], true) ? $format : 'csv';
    }

    /**
     * @return array<string, mixed>
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

    protected function profitLossDocument(Request $request, ReportPeriod $period): ReportDocument
    {
        $report = $this->profitLossData($request, $period);
        $isProfit = $report['net_profit'] >= 0;

        return ReportDocument::make(__('reports.profit_loss_statement'), $period, 'profit-loss-'.$period->slug())
            ->addSection(
                __('reports.profit_loss'),
                [__('reports.account_code'), __('reports.account'), __('reports.type'), __('reports.amount')],
                $report['lines']->map(fn ($line) => [
                    $line['code'],
                    $line['name'],
                    __('reports.'.$line['type']),
                    number_format($line['balance'], 2, '.', ''),
                ])->all(),
                numericColumns: [3],
                summary: [
                    __('reports.total_income') => number_format($report['income'], 2),
                    __('reports.total_expenses') => number_format($report['expense'], 2),
                    ($isProfit ? __('reports.net_profit') : __('reports.net_loss')) => number_format(abs($report['net_profit']), 2),
                ],
                columnWidths: [14, 34, 14, 16],
            );
    }

    protected function trialBalanceDocument(Request $request, ReportPeriod $period): ReportDocument
    {
        $rows = $this->reports->trialBalance($request->user()->business_id, $period->asOf()->toDateString());

        return ReportDocument::make(__('reports.trial_balance'), $period, 'trial-balance-'.$period->slug())
            ->addSection(
                __('reports.trial_balance'),
                [__('reports.account_code'), __('reports.account'), __('reports.type'), __('reports.debit'), __('reports.credit')],
                $rows->map(fn ($row) => [
                    $row['code'],
                    $row['name'],
                    __('reports.'.$row['type']),
                    number_format($row['debit'], 2, '.', ''),
                    number_format($row['credit'], 2, '.', ''),
                ])->all(),
                numericColumns: [3, 4],
                summary: [
                    __('reports.totals').' ('.__('reports.debit').')' => number_format($rows->sum('debit'), 2),
                    __('reports.totals').' ('.__('reports.credit').')' => number_format($rows->sum('credit'), 2),
                ],
                columnWidths: [14, 34, 14, 16, 16],
            );
    }

    protected function balanceSheetDocument(Request $request, ReportPeriod $period): ReportDocument
    {
        $report = $this->reports->balanceSheet($request->user()->business_id, $period->asOf()->toDateString());

        $rows = $report['lines']->map(fn ($line) => [
            $line['code'],
            $line['name'],
            __('reports.'.$line['type']),
            number_format($line['balance'], 2, '.', ''),
        ])->all();

        $rows[] = ['', __('reports.retained_earnings'), __('reports.equity'), number_format($report['retained_earnings'], 2, '.', '')];

        return ReportDocument::make(__('reports.balance_sheet'), $period, 'balance-sheet-'.$period->slug())
            ->addSection(
                __('reports.balance_sheet'),
                [__('reports.account_code'), __('reports.account'), __('reports.type'), __('reports.balance')],
                $rows,
                numericColumns: [3],
                summary: [
                    __('reports.total_assets') => number_format($report['assets'], 2),
                    __('reports.total_liabilities') => number_format($report['liabilities'], 2),
                    __('reports.total_equity') => number_format($report['equity'] + $report['retained_earnings'], 2),
                ],
                columnWidths: [14, 34, 14, 16],
            );
    }

    protected function cashBookDocument(Request $request, ReportPeriod $period): ReportDocument
    {
        $lines = $this->cashBookData($request, $period);

        return ReportDocument::make(__('reports.cash_book'), $period, 'cash-book-'.$period->slug())
            ->addSection(
                __('reports.cash_book'),
                [
                    __('reports.date_bs'), __('reports.date_ad'), __('reports.description'),
                    __('reports.cash_in'), __('reports.cash_out'), __('reports.balance'),
                ],
                $lines->map(fn ($line) => [
                    NepaliDate::format($line->entry_date),
                    Carbon::parse($line->entry_date)->toDateString(),
                    $line->description,
                    number_format((float) $line->debit, 2, '.', ''),
                    number_format((float) $line->credit, 2, '.', ''),
                    number_format($line->running_balance, 2, '.', ''),
                ])->all(),
                numericColumns: [3, 4, 5],
                columnWidths: [14, 14, 40, 14, 14, 16],
            );
    }

    protected function ledgerDocument(Request $request, ReportPeriod $period): ReportDocument
    {
        [, $account, $lines] = $this->ledgerData($request, $period);
        $name = $account ? $account->code.' — '.$account->name : __('reports.ledger');

        return ReportDocument::make(__('reports.ledger').': '.$name, $period, 'ledger-'.($account?->code ?? 'account').'-'.$period->slug())
            ->addSection(
                $name,
                [
                    __('reports.date_bs'), __('reports.date_ad'), __('reports.description'),
                    __('reports.debit'), __('reports.credit'), __('reports.balance'),
                ],
                $lines->map(fn ($line) => [
                    NepaliDate::format($line->entry_date),
                    Carbon::parse($line->entry_date)->toDateString(),
                    $line->description,
                    number_format((float) $line->debit, 2, '.', ''),
                    number_format((float) $line->credit, 2, '.', ''),
                    number_format($line->running_balance, 2, '.', ''),
                ])->all(),
                numericColumns: [3, 4, 5],
                columnWidths: [14, 14, 40, 14, 14, 16],
            );
    }

    /**
     * @return array{nepaliMonths: array<int, array{value: string, label: string}>}
     */
    protected function pickerData(): array
    {
        return ['nepaliMonths' => NepaliDate::recentMonths(24)];
    }
}
