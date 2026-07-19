<?php

namespace Modules\Sales\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Models\Sale;

class SaleApiController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $sales = Sale::where('status', 'completed')
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('completed_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('completed_at', '<=', $request->input('to')))
            ->orderByDesc('completed_at')
            ->paginate($request->integer('per_page', 25));

        return $this->respond(
            $sales->getCollection()->map($this->transformSummary(...)),
            meta: ['total' => $sales->total(), 'per_page' => $sales->perPage(), 'current_page' => $sales->currentPage()],
            links: ['next' => $sales->nextPageUrl(), 'prev' => $sales->previousPageUrl()],
        );
    }

    public function show(Sale $sale): JsonResponse
    {
        $sale->load(['items.product', 'payments.transaction', 'customer', 'cashier', 'branch']);

        return $this->respond([
            ...$this->transformSummary($sale),
            'discount_amount' => (float) $sale->discount_amount,
            'items' => $sale->items->map(fn ($item) => [
                'product_name' => $item->product->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ]),
            'payments' => $sale->payments->map(fn ($payment) => [
                'gateway_key' => $payment->transaction->gateway_key,
                'amount' => (float) $payment->amount,
            ]),
        ]);
    }

    protected function transformSummary(Sale $sale): array
    {
        return [
            'public_id' => $sale->public_id,
            'invoice_no' => $sale->invoice_no,
            'branch_id' => $sale->branch_id,
            'customer_name' => $sale->customer?->name,
            'cashier_name' => $sale->cashier?->name,
            'subtotal' => (float) $sale->subtotal,
            'tax_amount' => (float) $sale->tax_amount,
            'total_amount' => (float) $sale->total_amount,
            'status' => $sale->status,
            'completed_at' => $sale->completed_at?->toIso8601String(),
        ];
    }
}
