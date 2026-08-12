<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Nepali\AmountInWords;
use App\Support\Nepali\NepaliDate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Sales\Models\Sale;
use Modules\Settings\Services\SettingsService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReceiptController extends Controller
{
    public function __invoke(Sale $sale, Request $request, SettingsService $settings): Response
    {
        abort_unless($sale->business_id === $request->user()->business_id, HttpResponse::HTTP_FORBIDDEN);

        $sale->load(['items.product', 'payments.transaction', 'customer', 'cashier', 'branch']);

        $template = array_merge(
            ['header_note' => '', 'footer_text' => 'Thank you for shopping with us!', 'show_qr' => true],
            $settings->get($sale->business_id, 'receipt_template', []),
        );

        // The directive requires each print to be counted and every print
        // after the first to be marked as a copy, so the count is recorded
        // before the PDF is produced.
        $copyNumber = $sale->print_count;

        $sale->forceFill([
            'print_count' => $sale->print_count + 1,
            'first_printed_at' => $sale->first_printed_at ?? now(),
        ])->save();

        $business = $request->user()->business;

        $qrSvg = $template['show_qr']
            ? base64_encode(QrCode::format('svg')->size(120)->margin(0)->generate($sale->public_id))
            : null;

        $pdf = Pdf::loadView('sales::receipt', [
            'sale' => $sale,
            'business' => $business,
            'sellerPan' => $business?->pan_vat_number,
            'buyerPan' => $sale->buyer_pan ?? $sale->customer?->pan_vat_number,
            'invoiceDateBs' => NepaliDate::formatLong($sale->completed_at),
            'taxableAmount' => round((float) $sale->total_amount - (float) $sale->tax_amount, 2),
            'amountInWords' => AmountInWords::rupees((float) $sale->total_amount),
            'copyNumber' => $copyNumber,
            'qrSvg' => $qrSvg,
            'template' => $template,
        ])->setPaper([0, 0, 226.77, 700], 'portrait'); // ~80mm thermal width, tall enough for content

        return $pdf->stream("invoice-{$sale->invoice_no}.pdf");
    }
}
