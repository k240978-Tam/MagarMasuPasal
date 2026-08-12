<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Support\Nepali\NepaliDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Customers\Models\Customer;
use Modules\Inventory\Models\InventoryStock;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Models\Sale;
use Modules\Sales\Services\InvoiceNumberService;
use Modules\Sales\Services\SaleService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\Branch;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Tests\TestCase;

/**
 * Covers what the IRD Electronic Billing Directive requires of the billing
 * system itself: fiscal-year-scoped numbering that never reuses or skips a
 * number, buyer identity frozen onto the invoice, and cancellation that
 * retains the invoice instead of deleting it.
 */
class IrdComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_are_numbered_per_nepali_fiscal_year(): void
    {
        [$branch, $product, $cashier] = $this->shop();

        $first = $this->sell($branch, $product, $cashier);
        $second = $this->sell($branch, $product, $cashier);

        $fiscalYear = NepaliDate::fiscalYear(now());

        $this->assertSame($fiscalYear, $first->fiscal_year);
        $this->assertSame(1, $first->fiscal_sequence);
        $this->assertSame($fiscalYear.'-000001', $first->invoice_no);

        $this->assertSame(2, $second->fiscal_sequence);
        $this->assertSame($fiscalYear.'-000002', $second->invoice_no);
    }

    public function test_the_sequence_restarts_in_a_new_fiscal_year(): void
    {
        [$branch] = $this->shop();
        $numbers = app(InvoiceNumberService::class);

        // A sale already billed in the previous fiscal year must not push the
        // new year's sequence forward.
        Sale::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'terminal_id' => $this->terminalFor($branch),
            'cashier_id' => User::factory()->create(['business_id' => $branch->business_id])->id,
            'invoice_no' => '2082/83-000042',
            'fiscal_year' => '2082/83',
            'fiscal_sequence' => 42,
            'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => 100,
            'completed_at' => now(),
        ]);

        $reserved = $numbers->reserve($branch->business_id, NepaliDate::toAd(2083, 4, 1));

        $this->assertSame('2083/84', $reserved['fiscal_year']);
        $this->assertSame(1, $reserved['sequence']);
    }

    public function test_buyer_name_and_pan_are_frozen_onto_the_invoice(): void
    {
        [$branch, $product, $cashier] = $this->shop();

        $customer = Customer::create([
            'business_id' => $branch->business_id,
            'name' => 'Hotel Everest',
            'pan_vat_number' => '301234567',
        ]);

        $sale = $this->sell($branch, $product, $cashier, $customer->id);

        $this->assertSame('Hotel Everest', $sale->buyer_name);
        $this->assertSame('301234567', $sale->buyer_pan);

        // Editing the customer later must not rewrite an issued tax invoice.
        $customer->update(['name' => 'Hotel Everest Pvt. Ltd.', 'pan_vat_number' => '309999999']);

        $sale->refresh();
        $this->assertSame('Hotel Everest', $sale->buyer_name);
        $this->assertSame('301234567', $sale->buyer_pan);
    }

    public function test_cancelling_an_invoice_keeps_it_and_returns_stock(): void
    {
        [$branch, $product, $cashier] = $this->shop();

        $sale = $this->sell($branch, $product, $cashier);
        $invoiceNo = $sale->invoice_no;

        $stockAfterSale = (float) InventoryStock::withoutTenantScope()
            ->where('product_id', $product->id)->value('quantity_on_hand');

        app(SaleService::class)->cancelInvoice($sale, $cashier->id, 'Customer changed their mind');

        $sale->refresh();

        // The invoice and its number survive — nothing is deleted or reused.
        $this->assertNotNull(Sale::withoutTenantScope()->find($sale->id));
        $this->assertSame($invoiceNo, $sale->invoice_no);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame($cashier->id, $sale->cancelled_by);
        $this->assertSame('Customer changed their mind', $sale->cancellation_reason);

        $stockAfterCancel = (float) InventoryStock::withoutTenantScope()
            ->where('product_id', $product->id)->value('quantity_on_hand');
        $this->assertSame(1.0, round($stockAfterCancel - $stockAfterSale, 3));
    }

    public function test_an_invoice_cannot_be_cancelled_twice(): void
    {
        [$branch, $product, $cashier] = $this->shop();
        $sale = $this->sell($branch, $product, $cashier);

        app(SaleService::class)->cancelInvoice($sale, $cashier->id, 'Duplicate billing');

        $this->expectExceptionMessage('already been cancelled');
        app(SaleService::class)->cancelInvoice($sale->fresh(), $cashier->id, 'Again');
    }

    public function test_the_sales_book_reports_a_gap_in_the_invoice_series(): void
    {
        [$branch, $product, $cashier] = $this->shop();
        $this->sell($branch, $product, $cashier);
        $third = $this->sell($branch, $product, $cashier);

        // Simulate a number vanishing from the series — exactly what the
        // check exists to surface to an auditor.
        $third->forceFill(['fiscal_sequence' => 3])->save();

        $missing = app(InvoiceNumberService::class)
            ->missingSequences($branch->business_id, NepaliDate::fiscalYear(now()));

        $this->assertSame([2], $missing);
    }

    /**
     * @return array{0: Branch, 1: Product, 2: User}
     */
    private function shop(): array
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['business_id' => $businessId, 'name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Mutton Curry Cut', 'unit_id' => $unit->id,
            'cost_price' => 900, 'selling_price' => 1150,
        ]);

        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 50, 'quantity_remaining' => 50, 'unit_cost' => 900, 'received_at' => now(),
        ]);
        InventoryStock::create([
            'business_id' => $businessId, 'branch_id' => $branch->id,
            'product_id' => $product->id, 'quantity_on_hand' => 50,
        ]);

        $cashier = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branch->id]);

        return [$branch, $product, $cashier];
    }

    private function sell(Branch $branch, Product $product, User $cashier, ?int $customerId = null): Sale
    {
        return app(SaleService::class)->finalize(new FinalizeSaleDTO(
            businessId: $branch->business_id,
            branchId: $branch->id,
            terminalId: $this->terminalFor($branch),
            cashierId: $cashier->id,
            items: [[
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 1150,
                'discount_amount' => 0,
                'tax_amount' => 0,
            ]],
            payments: [['gateway_key' => 'cash', 'amount' => 1150]],
            customerId: $customerId,
            saleType: 'retail',
        ));
    }

    private function terminalFor(Branch $branch): int
    {
        return BranchTerminal::firstOrCreate(
            ['business_id' => $branch->business_id, 'branch_id' => $branch->id, 'name' => 'Counter 1'],
        )->id;
    }
}
