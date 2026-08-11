<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\AccountingReportService;
use Modules\Accounting\Services\ChartOfAccountsService;

class FinancialReportController extends Controller
{
    public function __construct(protected AccountingReportService $reports) {}

    public function profitLoss(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('accounting::reports.profit-loss', [
            'report' => $this->reports->profitAndLoss($request->user()->business_id, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function trialBalance(Request $request): View
    {
        $asOf = $request->query('as_of', now()->toDateString());
        $rows = $this->reports->trialBalance($request->user()->business_id, $asOf);

        return view('accounting::reports.trial-balance', [
            'rows' => $rows,
            'totalDebit' => round($rows->sum('debit'), 2),
            'totalCredit' => round($rows->sum('credit'), 2),
            'asOf' => $asOf,
        ]);
    }

    public function balanceSheet(Request $request): View
    {
        $asOf = $request->query('as_of', now()->toDateString());

        return view('accounting::reports.balance-sheet', [
            'report' => $this->reports->balanceSheet($request->user()->business_id, $asOf),
            'asOf' => $asOf,
        ]);
    }

    public function cashBook(Request $request): View
    {
        [$from, $to] = $this->range($request);

        return view('accounting::reports.cash-book', [
            'lines' => $this->reports->cashBook($request->user()->business_id, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function ledger(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $businessId = $request->user()->business_id;

        $accounts = ChartOfAccount::withoutTenantScope()
            ->where('business_id', $businessId)
            ->orderBy('code')
            ->get(['code', 'name', 'type']);

        $code = (string) $request->query('account', ChartOfAccountsService::CASH);
        $account = $accounts->firstWhere('code', $code) ?? $accounts->first();

        return view('accounting::reports.ledger', [
            'accounts' => $accounts,
            'account' => $account,
            'lines' => $account ? $this->reports->generalLedger($businessId, $account->code, $from, $to) : collect(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @return array{string, string}
     */
    protected function range(Request $request): array
    {
        return [
            (string) $request->query('from', now()->startOfMonth()->toDateString()),
            (string) $request->query('to', now()->toDateString()),
        ];
    }
}
