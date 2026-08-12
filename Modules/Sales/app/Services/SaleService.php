<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Services\InventoryService;
use Modules\PaymentManager\DTOs\CreatePaymentDTO;
use Modules\PaymentManager\Models\PaymentTransaction;
use Modules\PaymentManager\Services\PaymentManager;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Events\SaleCompleted;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleReturn;
use RuntimeException;

/**
 * Finalizes a POS cart into a completed sale: deducts stock FIFO through
 * InventoryService, captures payment(s) through PaymentManager, and fires
 * SaleCompleted. This is the one place those three modules meet — POS never
 * calls Inventory or PaymentManager directly.
 */
class SaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PaymentManager $payments,
        protected InvoiceNumberService $invoiceNumbers,
    ) {}

    public function finalize(FinalizeSaleDTO $dto): Sale
    {
        return DB::transaction(function () use ($dto) {
            [$subtotal, $discountTotal, $taxTotal, $lines] = $this->priceItems($dto->items);
            $totalAmount = round($subtotal - $discountTotal + $taxTotal, 2);

            $paymentsTotal = round(array_sum(array_column($dto->payments, 'amount')), 2);

            if ($dto->saleType === 'retail' && $paymentsTotal + 0.01 < $totalAmount) {
                throw new RuntimeException('Payment total is less than the sale total.');
            }

            $number = $this->invoiceNumbers->reserve($dto->businessId);
            $buyer = $dto->customerId
                ? Customer::withoutTenantScope()->find($dto->customerId)
                : null;

            $sale = Sale::create([
                'business_id' => $dto->businessId,
                'branch_id' => $dto->branchId,
                'terminal_id' => $dto->terminalId,
                'customer_id' => $dto->customerId,
                // Snapshotted, not read through the relation at print time:
                // a tax invoice must keep the buyer identity it was issued
                // with even if the customer record is edited later.
                'buyer_name' => $buyer?->name,
                'buyer_pan' => $buyer?->pan_vat_number,
                'cashier_id' => $dto->cashierId,
                'invoice_no' => $number['invoice_no'],
                'fiscal_year' => $number['fiscal_year'],
                'fiscal_sequence' => $number['sequence'],
                'subtotal' => $subtotal,
                'discount_amount' => $discountTotal,
                'tax_amount' => $taxTotal,
                'total_amount' => $totalAmount,
                'sale_type' => $dto->saleType,
                'notes' => $dto->notes,
                'completed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $movements = $this->inventory->deductFifo(
                    $dto->businessId,
                    $dto->branchId,
                    $line['product_id'],
                    $line['quantity'],
                    'sale',
                    Sale::class,
                    $sale->id,
                );

                $sale->items()->create([
                    'business_id' => $dto->businessId,
                    'product_id' => $line['product_id'],
                    'batch_id' => count($movements) === 1 ? $movements[0]->batch_id : null,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'cost_price_at_sale' => $this->weightedAverageCost($movements),
                    'discount_amount' => $line['discount_amount'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            foreach ($dto->payments as $payment) {
                $result = $this->payments->createPayment(new CreatePaymentDTO(
                    businessId: $dto->businessId,
                    amount: $payment['amount'],
                    gatewayKey: $payment['gateway_key'],
                    payableType: Sale::class,
                    payableId: $sale->id,
                ));

                $transaction = PaymentTransaction::withoutTenantScope()
                    ->where('public_id', $result->transactionPublicId)->firstOrFail();

                $sale->payments()->create([
                    'business_id' => $dto->businessId,
                    'payment_transaction_id' => $transaction->id,
                    'amount' => $payment['amount'],
                ]);
            }

            if ($dto->saleType === 'credit' && $dto->customerId) {
                $due = $totalAmount - $paymentsTotal;

                if ($due > 0) {
                    Customer::withoutTenantScope()->whereKey($dto->customerId)->increment('current_due', $due);
                }
            }

            SaleCompleted::dispatch($sale->fresh(['items', 'payments.transaction']));

            return $sale->fresh(['items.product', 'payments.transaction', 'customer']);
        });
    }

    public function voidSale(Sale $sale, int $userId, string $reason = 'Voided by cashier'): SaleReturn
    {
        if ($sale->status !== 'completed') {
            throw new RuntimeException('Only a completed sale can be voided.');
        }

        return $this->refund($sale, $sale->items->map(fn ($item) => [
            'sale_item_id' => $item->id,
            'quantity' => (float) $item->quantity,
        ])->all(), $reason, 'cash', $userId, voidSale: true);
    }

    /**
     * @param  array<int, array{sale_item_id: int, quantity: float}>  $lines
     */
    public function refund(Sale $sale, array $lines, string $reason, string $refundMethod, int $approvedBy, bool $voidSale = false): SaleReturn
    {
        return DB::transaction(function () use ($sale, $lines, $reason, $refundMethod, $approvedBy, $voidSale) {
            $refundTotal = 0.0;

            $return = SaleReturn::create([
                'business_id' => $sale->business_id,
                'sale_id' => $sale->id,
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'refund_amount' => 0,
                'approved_by' => $approvedBy,
            ]);

            foreach ($lines as $line) {
                $saleItem = $sale->items()->findOrFail($line['sale_item_id']);
                $quantity = (float) $line['quantity'];
                $unitLineValue = (float) $saleItem->line_total / (float) $saleItem->quantity;
                $lineRefund = round($unitLineValue * $quantity, 2);

                $return->items()->create([
                    'business_id' => $sale->business_id,
                    'sale_item_id' => $saleItem->id,
                    'quantity' => $quantity,
                    'refund_amount' => $lineRefund,
                ]);

                $this->inventory->returnToStock(
                    $sale->business_id,
                    $sale->branch_id,
                    $saleItem->product_id,
                    $quantity,
                    $saleItem->batch_id,
                    SaleReturn::class,
                    $return->id,
                );

                $refundTotal += $lineRefund;
            }

            $return->update(['refund_amount' => round($refundTotal, 2)]);

            $fullyRefunded = $sale->items->sum('quantity') <= $sale->returns()->with('items')->get()
                ->flatMap(fn (SaleReturn $return) => $return->items)->sum('quantity');

            $sale->update([
                'status' => $voidSale ? 'void' : ($fullyRefunded ? 'refunded' : 'partially_refunded'),
            ]);

            return $return->fresh('items');
        });
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: array}
     */
    protected function priceItems(array $items): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $lines = [];

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $discount = (float) ($item['discount_amount'] ?? 0);
            $tax = (float) ($item['tax_amount'] ?? 0);

            $gross = round($quantity * $unitPrice, 2);
            $lineTotal = round($gross - $discount + $tax, 2);

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;

            $lines[] = [
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
            ];
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($taxTotal, 2), $lines];
    }

    protected function weightedAverageCost(array $movements): float
    {
        if (empty($movements)) {
            return 0;
        }

        $totalQty = array_sum(array_map(fn ($m) => abs((float) $m->quantity_change), $movements));

        if ($totalQty <= 0) {
            return 0;
        }

        $totalCost = array_sum(array_map(
            fn ($m) => abs((float) $m->quantity_change) * (float) $m->unit_cost_at_movement,
            $movements,
        ));

        return round($totalCost / $totalQty, 2);
    }

    /**
     * Cancel an issued tax invoice, per the IRD Electronic Billing Directive:
     * the invoice and its number are retained (never deleted or reused), a
     * reason and the responsible user are recorded, and the cancellation is
     * reportable — while stock and ledger effects are reversed exactly as a
     * void does.
     */
    public function cancelInvoice(Sale $sale, int $userId, string $reason): Sale
    {
        if ($sale->cancelled_at !== null) {
            throw new RuntimeException('This invoice has already been cancelled.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($sale, $userId, $reason) {
            if ($sale->status === 'completed') {
                $this->voidSale($sale, $userId, $reason);
                $sale->refresh();
            }

            $sale->update([
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => trim($reason),
                // A cancelled invoice must reach IRD too, so the bill is
                // marked inactive on their side rather than left dangling.
                'ird_sync_status' => 'pending',
            ]);

            return $sale->fresh();
        });
    }
}
