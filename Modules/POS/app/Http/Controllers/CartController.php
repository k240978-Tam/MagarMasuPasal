<?php

namespace Modules\POS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\POS\Http\Requests\AddItemRequest;
use Modules\POS\Http\Requests\CheckoutRequest;
use Modules\POS\Http\Requests\SetCustomerRequest;
use Modules\POS\Http\Requests\SetDiscountRequest;
use Modules\POS\Http\Requests\UpdateItemQuantityRequest;
use Modules\POS\Models\HeldBill;
use Modules\POS\Services\CartService;
use Modules\Products\Models\Product;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Services\SaleService;
use Modules\Tenancy\Models\BranchTerminal;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function show(BranchTerminal $terminal): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->getState($terminal));
    }

    public function start(BranchTerminal $terminal, Request $request): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->startSession($terminal, $request->user()));
    }

    public function addItem(BranchTerminal $terminal, AddItemRequest $request): JsonResponse
    {
        $this->assertAccess($terminal);

        $product = Product::withoutTenantScope()
            ->where('business_id', $terminal->business_id)
            ->with('unit')
            ->findOrFail($request->integer('product_id'));

        return response()->json($this->cart->addItem($terminal, $product, (float) $request->input('quantity')));
    }

    public function scanBarcode(BranchTerminal $terminal, Request $request): JsonResponse
    {
        $this->assertAccess($terminal);
        $request->validate(['barcode' => ['required', 'string']]);

        $product = Product::withoutTenantScope()
            ->where('business_id', $terminal->business_id)
            ->where('barcode', $request->string('barcode'))
            ->where('status', 'active')
            ->with('unit')
            ->first();

        if (! $product) {
            return response()->json(['message' => 'No product matches that barcode.'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($this->cart->addItem($terminal, $product, 1));
    }

    public function updateItem(BranchTerminal $terminal, int $product, UpdateItemQuantityRequest $request): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->updateItemQuantity($terminal, $product, (float) $request->input('quantity')));
    }

    public function removeItem(BranchTerminal $terminal, int $product): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->removeItem($terminal, $product));
    }

    public function setDiscount(BranchTerminal $terminal, SetDiscountRequest $request): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->setDiscountPercent($terminal, (float) $request->input('percent')));
    }

    public function setCustomer(BranchTerminal $terminal, SetCustomerRequest $request): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->setCustomer($terminal, $request->input('customer_id')));
    }

    public function hold(BranchTerminal $terminal): JsonResponse
    {
        $this->assertAccess($terminal);

        $bill = $this->cart->hold($terminal);

        return response()->json(['held_bill_id' => $bill->public_id]);
    }

    public function heldBills(BranchTerminal $terminal): JsonResponse
    {
        $this->assertAccess($terminal);

        $bills = HeldBill::withoutTenantScope()
            ->where('business_id', $terminal->business_id)
            ->where('branch_id', $terminal->branch_id)
            ->orderByDesc('held_at')
            ->get(['public_id', 'cart_snapshot', 'held_at']);

        return response()->json($bills);
    }

    public function resume(BranchTerminal $terminal, string $heldBill): JsonResponse
    {
        $this->assertAccess($terminal);

        $bill = HeldBill::withoutTenantScope()
            ->where('business_id', $terminal->business_id)
            ->where('public_id', $heldBill)
            ->firstOrFail();

        return response()->json($this->cart->resume($terminal, $bill));
    }

    public function checkout(BranchTerminal $terminal, CheckoutRequest $request, SaleService $sales): JsonResponse
    {
        $this->assertAccess($terminal);

        $cart = $this->cart->getState($terminal);

        if (empty($cart['items'])) {
            return response()->json(['message' => 'Cart is empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->cart->setStatus($terminal, 'awaiting_payment');

        $dto = new FinalizeSaleDTO(
            businessId: $terminal->business_id,
            branchId: $terminal->branch_id,
            terminalId: $terminal->id,
            cashierId: $request->user()->id,
            items: array_map(fn ($item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => round(($item['quantity'] * $item['unit_price']) * (($cart['discount_percent'] ?? 0) / 100), 2),
            ], array_values($cart['items'])),
            payments: $request->input('payments') ?? [],
            customerId: $cart['customer_id'],
            saleType: $request->input('sale_type'),
        );

        $sale = $sales->finalize($dto);

        $this->cart->setStatus($terminal, 'payment_success');

        return response()->json(['sale' => [
            'public_id' => $sale->public_id,
            'invoice_no' => $sale->invoice_no,
            'total_amount' => $sale->total_amount,
        ]]);
    }

    public function complete(BranchTerminal $terminal): JsonResponse
    {
        $this->assertAccess($terminal);

        return response()->json($this->cart->clear($terminal));
    }

    protected function assertAccess(BranchTerminal $terminal): void
    {
        abort_unless(
            $terminal->business_id === request()->user()->business_id,
            Response::HTTP_FORBIDDEN,
        );
    }
}
