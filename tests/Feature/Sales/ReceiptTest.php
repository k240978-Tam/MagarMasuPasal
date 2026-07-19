<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Products\Models\Product;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Tests\TestCase;

/**
 * Proves the receipt endpoint actually streams a valid PDF (not just a
 * 200 with an error page) and enforces tenant isolation — a user must
 * never be able to fetch another business's receipt by guessing a
 * public_id.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSale(int $businessId, int $branchId): Sale
    {
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken Boneless', 'unit_id' => $unit->id, 'selling_price' => 480]);
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branchId, 'name' => 'Counter 1']);
        $cashier = User::factory()->create(['business_id' => $businessId]);

        $sale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branchId, 'terminal_id' => $terminal->id,
            'cashier_id' => $cashier->id,
            'invoice_no' => 'INV-000001', 'subtotal' => 960, 'discount_amount' => 0,
            'tax_amount' => 0, 'total_amount' => 960, 'status' => 'completed',
            'sale_type' => 'retail', 'completed_at' => now(),
        ]);

        SaleItem::create([
            'business_id' => $businessId, 'sale_id' => $sale->id, 'product_id' => $product->id,
            'quantity' => 2, 'unit_price' => 480, 'cost_price_at_sale' => 400, 'line_total' => 960,
        ]);

        return $sale;
    }

    public function test_receipt_streams_a_valid_pdf_for_the_sales_own_business(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $sale = $this->makeSale($businessId, $branch->id);
        $user = User::factory()->create(['business_id' => $businessId]);

        $response = $this->actingAs($user)->get("/sales/{$sale->public_id}/receipt");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_receipt_is_forbidden_for_a_sale_belonging_to_another_business(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();
        $this->assertNotSame($branchA->business_id, $branchB->business_id);

        $sale = $this->makeSale($branchA->business_id, $branchA->id);
        $otherUser = User::factory()->create(['business_id' => $branchB->business_id]);

        // 404, not 403: TenantScope filters the cross-tenant sale out of
        // route-model binding before the controller's own abort_unless
        // check ever runs — the more secure outcome, since it doesn't even
        // confirm the sale exists.
        $this->actingAs($otherUser)->get("/sales/{$sale->public_id}/receipt")->assertNotFound();
    }
}
