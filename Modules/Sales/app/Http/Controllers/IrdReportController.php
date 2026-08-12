<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Nepali\NepaliDate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\CbmsSyncService;
use Modules\Sales\Services\InvoiceNumberService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The sales book (बिक्री खाता) IRD requires a VAT-registered taxpayer to
 * maintain: every invoice of a fiscal year in number order, including
 * cancelled ones, with the taxable/VAT split.
 */
class IrdReportController extends Controller
{
    public function __construct(
        protected InvoiceNumberService $invoiceNumbers,
        protected CbmsSyncService $cbms,
    ) {}

    public function salesBook(Request $request): View
    {
        $businessId = $request->user()->business_id;
        $fiscalYears = $this->invoiceNumbers->fiscalYears($businessId);
        $fiscalYear = (string) $request->query('fiscal_year', $fiscalYears[0] ?? NepaliDate::fiscalYear(now()));

        return view('sales::ird.sales-book', [
            'sales' => $this->rows($businessId, $fiscalYear),
            'totals' => $this->invoiceNumbers->fiscalYearTotals($businessId, $fiscalYear),
            'missingSequences' => $this->invoiceNumbers->missingSequences($businessId, $fiscalYear),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => $fiscalYears ?: [$fiscalYear],
            'cbmsEnabled' => $this->cbms->enabled(),
            'unsyncedCount' => Sale::where('business_id', $businessId)
                ->where('fiscal_year', $fiscalYear)
                ->whereIn('ird_sync_status', ['pending', 'failed'])
                ->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $businessId = $request->user()->business_id;
        $fiscalYear = (string) $request->query('fiscal_year', NepaliDate::fiscalYear(now()));
        $rows = $this->rows($businessId, $fiscalYear);

        $filename = 'sales-book-'.str_replace('/', '-', $fiscalYear).'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'S.N.',
                'Invoice Date (BS)',
                'Invoice Date (AD)',
                'Invoice No.',
                'Buyer Name',
                'Buyer PAN',
                'Total Sales',
                'Tax Exempted Sales',
                'Taxable Amount',
                'VAT',
                'Status',
            ]);

            foreach ($rows as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['date_bs'],
                    $row['date_ad'],
                    $row['invoice_no'],
                    $row['buyer_name'],
                    $row['buyer_pan'],
                    number_format($row['total'], 2, '.', ''),
                    number_format($row['exempt'], 2, '.', ''),
                    number_format($row['taxable'], 2, '.', ''),
                    number_format($row['vat'], 2, '.', ''),
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rows(int $businessId, string $fiscalYear): array
    {
        return Sale::where('business_id', $businessId)
            ->where('fiscal_year', $fiscalYear)
            ->with('customer')
            ->orderBy('fiscal_sequence')
            ->get()
            ->map(function (Sale $sale) {
                $total = round((float) $sale->total_amount, 2);
                $vat = round((float) $sale->tax_amount, 2);
                $taxable = $vat > 0 ? round($total - $vat, 2) : 0.0;

                return [
                    'public_id' => $sale->public_id,
                    'date_bs' => NepaliDate::format($sale->completed_at),
                    'date_ad' => $sale->completed_at->toDateString(),
                    'invoice_no' => $sale->invoice_no,
                    'buyer_name' => $sale->buyer_name ?: ($sale->customer?->name ?: 'Cash Sale'),
                    'buyer_pan' => $sale->buyer_pan ?: '',
                    'total' => $total,
                    'vat' => $vat,
                    'taxable' => $taxable,
                    'exempt' => round($total - $taxable - $vat, 2),
                    'status' => $sale->cancelled_at ? 'Cancelled' : 'Active',
                    'sync_status' => $sale->ird_sync_status,
                    'cancellation_reason' => $sale->cancellation_reason,
                ];
            })
            ->all();
    }
}
