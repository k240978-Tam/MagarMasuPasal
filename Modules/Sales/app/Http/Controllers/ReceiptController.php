<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Sales\Models\Sale;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReceiptController extends Controller
{
    public function __invoke(Sale $sale, Request $request): Response
    {
        abort_unless($sale->business_id === $request->user()->business_id, HttpResponse::HTTP_FORBIDDEN);

        $sale->load(['items.product', 'payments.transaction', 'customer', 'cashier', 'branch']);

        $qrSvg = base64_encode(QrCode::format('svg')->size(120)->margin(0)->generate($sale->public_id));

        $pdf = Pdf::loadView('sales::receipt', [
            'sale' => $sale,
            'business' => $request->user()->business,
            'qrSvg' => $qrSvg,
        ])->setPaper([0, 0, 226.77, 700], 'portrait'); // ~80mm thermal width, tall enough for content

        return $pdf->stream("receipt-{$sale->invoice_no}.pdf");
    }
}
