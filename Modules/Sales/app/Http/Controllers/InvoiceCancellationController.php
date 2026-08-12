<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\SaleService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class InvoiceCancellationController extends Controller
{
    public function __invoke(Request $request, Sale $sale, SaleService $sales): RedirectResponse
    {
        abort_unless($sale->business_id === $request->user()->business_id, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'IRD requires a reason for cancelling an invoice.',
            'reason.min' => 'Give a specific reason — an auditor has to be able to read it.',
        ]);

        try {
            $sales->cancelInvoice($sale, $request->user()->id, $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "Invoice {$sale->invoice_no} cancelled. The number is retained and reported as cancelled.");
    }
}
