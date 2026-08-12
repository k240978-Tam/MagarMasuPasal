<?php

namespace Modules\Sales\Services;

use App\Support\Nepali\NepaliDate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Models\Sale;

/**
 * Fiscal-year-scoped invoice numbering, per the IRD Electronic Billing
 * Directive: the sequence restarts at 1 each Nepali fiscal year (Shrawan 1),
 * numbers are assigned by the system only, and no number may be reused or
 * skipped — a cancelled invoice keeps its number.
 */
class InvoiceNumberService
{
    /**
     * Reserve the next number for a business's current fiscal year.
     *
     * Callers must already be inside the sale transaction: the row lock here
     * is what stops two terminals selling at the same moment from claiming
     * the same sequence.
     *
     * @return array{fiscal_year: string, sequence: int, invoice_no: string}
     */
    public function reserve(int $businessId, ?CarbonInterface $date = null): array
    {
        $fiscalYear = NepaliDate::fiscalYear($date ?? now());

        $lastSequence = (int) Sale::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('fiscal_year', $fiscalYear)
            ->lockForUpdate()
            ->max('fiscal_sequence');

        $sequence = $lastSequence + 1;

        return [
            'fiscal_year' => $fiscalYear,
            'sequence' => $sequence,
            'invoice_no' => $this->format($fiscalYear, $sequence),
        ];
    }

    /**
     * Invoice number as printed, e.g. "2083/84-000123".
     */
    public function format(string $fiscalYear, int $sequence): string
    {
        return $fiscalYear.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Gap check for the fiscal year: the directive expects an unbroken
     * sequence, so a missing number is a red flag an auditor will ask about.
     *
     * @return array<int, int> missing sequence numbers, in order
     */
    public function missingSequences(int $businessId, string $fiscalYear): array
    {
        $used = Sale::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('fiscal_year', $fiscalYear)
            ->orderBy('fiscal_sequence')
            ->pluck('fiscal_sequence')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->all();

        if ($used === []) {
            return [];
        }

        $expected = range(1, max($used));

        return array_values(array_diff($expected, $used));
    }

    /**
     * @return array<int, string> fiscal years this business has billed in, newest first
     */
    public function fiscalYears(int $businessId): array
    {
        return Sale::withoutTenantScope()
            ->where('business_id', $businessId)
            ->whereNotNull('fiscal_year')
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->all();
    }

    /**
     * Total sales and VAT for a fiscal year, as filed on the VAT return.
     *
     * @return array{taxable: float, vat: float, exempt: float, total: float}
     */
    public function fiscalYearTotals(int $businessId, string $fiscalYear): array
    {
        $row = Sale::withoutTenantScope()
            ->where('business_id', $businessId)
            ->where('fiscal_year', $fiscalYear)
            ->whereNull('cancelled_at')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(tax_amount), 0) as vat')
            ->first();

        $total = (float) ($row->total ?? 0);
        $vat = (float) ($row->vat ?? 0);

        // Taxable base is what VAT was charged on; everything else in the
        // invoice total is exempt turnover (fresh meat and vegetables are
        // typically VAT-exempt in Nepal).
        $taxable = $vat > 0
            ? (float) DB::table('sales')
                ->where('business_id', $businessId)
                ->where('fiscal_year', $fiscalYear)
                ->whereNull('cancelled_at')
                ->where('tax_amount', '>', 0)
                ->sum(DB::raw('total_amount - tax_amount'))
            : 0.0;

        return [
            'taxable' => round($taxable, 2),
            'vat' => round($vat, 2),
            'exempt' => round($total - $taxable - $vat, 2),
            'total' => round($total, 2),
        ];
    }
}
