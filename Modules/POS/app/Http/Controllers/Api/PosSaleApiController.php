<?php

namespace Modules\POS\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\POS\Http\Concerns\HandlesIdempotency;
use Modules\POS\Http\Requests\FinalizeApiSaleRequest;
use Modules\Products\Models\Product;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Services\SaleService;
use Modules\Settings\Services\TaxCalculationService;
use Modules\Tenancy\Models\BranchTerminal;

/**
 * The mobile-client equivalent of the web Cashier POS's checkout flow —
 * but stateless: a client builds its own cart and submits the whole sale
 * in one call, rather than mutating a server-side cart line by line. Prices
 * and tax are always recomputed server-side from the current Product/TaxRule
 * data, never trusted from the request body.
 */
class PosSaleApiController extends Controller
{
    use ApiResponds, HandlesIdempotency;

    public function store(
        BranchTerminal $terminal,
        FinalizeApiSaleRequest $request,
        SaleService $sales,
        TaxCalculationService $tax,
    ): JsonResponse {
        abort_unless($terminal->business_id === $request->user()->business_id, 403);

        return $this->withIdempotency($request, function () use ($terminal, $request, $sales, $tax) {
            $discountPercent = (float) $request->input('discount_percent', 0);
            $items = [];

            foreach ($request->input('items') as $line) {
                $product = Product::findOrFail($line['product_id']);
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $product->selling_price;

                $gross = round($quantity * $unitPrice, 2);
                $discountAmount = round($gross * ($discountPercent / 100), 2);
                $rate = $tax->rateForRule($terminal->business_id, $product->tax_rule_id);
                $taxAmount = round(($gross - $discountAmount) * ($rate / 100), 2);

                $items[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                ];
            }

            $sale = $sales->finalize(new FinalizeSaleDTO(
                businessId: $terminal->business_id,
                branchId: $terminal->branch_id,
                terminalId: $terminal->id,
                cashierId: $request->user()->id,
                items: $items,
                payments: $request->input('payments', []),
                customerId: $request->input('customer_id'),
                saleType: $request->input('sale_type'),
                notes: $request->input('notes'),
            ));

            return $this->respond([
                'public_id' => $sale->public_id,
                'invoice_no' => $sale->invoice_no,
                'total_amount' => (float) $sale->total_amount,
                'status' => $sale->status,
            ], status: 201);
        });
    }
}
