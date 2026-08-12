<?php

namespace Modules\Sales\Services;

use App\Support\Nepali\NepaliDate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Sales\Models\Sale;
use Throwable;

/**
 * Pushes issued invoices to IRD's Central Billing Monitoring System.
 *
 * CBMS credentials are issued per taxpayer, and only after the billing
 * software has been submitted to IRD and listed as approved — so this stays
 * disabled by default. When disabled, invoices are marked "disabled" rather
 * than "failed": nothing is owed to IRD yet, and a shop below the real-time
 * threshold never needs it. Payload field names follow IRD's documented
 * bill-entry schema.
 */
class CbmsSyncService
{
    public function enabled(): bool
    {
        return (bool) config('ird.cbms.enabled')
            && filled(config('ird.cbms.username'))
            && filled(config('ird.cbms.password'))
            && filled(config('ird.cbms.seller_pan'));
    }

    /**
     * Send one invoice. Returns true when IRD accepted it.
     */
    public function sync(Sale $sale): bool
    {
        if (! $this->enabled()) {
            $sale->forceFill([
                'ird_sync_status' => 'disabled',
                'ird_sync_error' => null,
            ])->save();

            return false;
        }

        try {
            $response = Http::timeout((int) config('ird.cbms.timeout'))
                ->retry((int) config('ird.cbms.retry_attempts'), 200, throw: false)
                ->acceptJson()
                ->post((string) config('ird.cbms.endpoint'), $this->payload($sale));

            if ($response->successful()) {
                $sale->forceFill([
                    'ird_sync_status' => 'synced',
                    'ird_synced_at' => now(),
                    'ird_sync_error' => null,
                ])->save();

                return true;
            }

            $this->markFailed($sale, 'IRD responded '.$response->status().': '.$response->body());
        } catch (Throwable $e) {
            $this->markFailed($sale, $e->getMessage());
        }

        return false;
    }

    /**
     * The bill payload IRD expects. Amounts are the invoice as issued;
     * a cancelled invoice is reported with is_bill_active false so IRD's
     * copy matches the shop's books.
     *
     * @return array<string, mixed>
     */
    public function payload(Sale $sale): array
    {
        $vat = round((float) $sale->tax_amount, 2);
        $total = round((float) $sale->total_amount, 2);
        $taxable = $vat > 0 ? round($total - $vat, 2) : 0.0;

        return [
            'username' => config('ird.cbms.username'),
            'password' => config('ird.cbms.password'),
            'seller_pan' => config('ird.cbms.seller_pan'),
            'buyer_pan' => $sale->buyer_pan ?: '',
            'buyer_name' => $sale->buyer_name ?: 'Cash Sale',
            'fiscal_year' => $sale->fiscal_year,
            'invoice_number' => $sale->invoice_no,
            'invoice_date' => NepaliDate::format($sale->completed_at),
            'total_sales' => $total,
            'taxable_sales_vat' => $taxable,
            'vat' => $vat,
            'excisable_amount' => 0,
            'excise' => 0,
            'tax_exempted_sales' => round($total - $taxable - $vat, 2),
            'export_sales' => 0,
            'is_realtime' => true,
            'is_bill_printed' => $sale->print_count > 0,
            'is_bill_active' => $sale->cancelled_at === null,
            'printed_time' => optional($sale->first_printed_at)->format('Y-m-d H:i:s'),
            'entered_by' => $sale->cashier?->name,
            'datetime_client' => now()->format('Y-m-d H:i:s'),
        ];
    }

    protected function markFailed(Sale $sale, string $message): void
    {
        // Truncated: IRD error bodies can be long, and the column is for
        // diagnosis, not archival.
        $sale->forceFill([
            'ird_sync_status' => 'failed',
            'ird_sync_error' => mb_substr($message, 0, 1000),
        ])->save();

        Log::warning('CBMS sync failed', [
            'sale_id' => $sale->id,
            'invoice_no' => $sale->invoice_no,
            'error' => $message,
        ]);
    }
}
