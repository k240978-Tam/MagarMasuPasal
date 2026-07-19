<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\CashRegister\Services\CashSessionService;
use Modules\Expenses\Models\ExpenseCategory;
use Modules\Expenses\Services\ExpenseService;
use Modules\Inventory\Models\InventoryStock;
use Modules\Products\Models\Product;
use Modules\Purchases\Models\PurchaseBatch;
use Modules\Purchases\Services\PurchaseService;
use Modules\Sales\DTOs\FinalizeSaleDTO;
use Modules\Sales\Services\SaleService;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Tests\TestCase;

/**
 * Proves the roadmap's Phase 3 exit criteria: every sale/purchase/expense
 * automatically produces a balanced journal entry, purely by dispatching the
 * same domain events the rest of the app already fires — Accounting is
 * never called directly by Sales/Purchases/Expenses.
 */
class AutoPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sale_posts_a_balanced_revenue_and_cogs_entry(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id]);
        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => 420, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 10]);
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $businessId]);

        app(SaleService::class)->finalize(new FinalizeSaleDTO(
            businessId: $businessId, branchId: $branch->id, terminalId: $terminal->id, cashierId: $user->id,
            items: [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 480]],
            payments: [['gateway_key' => 'cash', 'amount' => 960]],
        ));

        $entry = JournalEntry::withoutTenantScope()->where('business_id', $businessId)->firstOrFail();
        $lines = $entry->lines()->with('account')->get();

        $this->assertEqualsWithDelta($lines->sum('debit'), $lines->sum('credit'), 0.01);
        $this->assertSame('960.00', $lines->firstWhere('account.code', '4000')->credit);
        $this->assertSame('960.00', $lines->firstWhere('account.code', '1000')->debit);
        $this->assertSame('840.00', $lines->firstWhere('account.code', '5000')->debit); // 2 * 420 COGS
        $this->assertSame('840.00', $lines->firstWhere('account.code', '1200')->credit);
    }

    public function test_a_credit_sale_debits_accounts_receivable_for_the_unpaid_portion(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Piece', 'symbol' => 'pcs', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Eggs', 'unit_id' => $unit->id]);
        PurchaseBatch::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'quantity_received' => 5, 'quantity_remaining' => 5, 'unit_cost' => 180, 'received_at' => now(),
        ]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $businessId]);

        app(SaleService::class)->finalize(new FinalizeSaleDTO(
            businessId: $businessId, branchId: $branch->id, terminalId: $terminal->id, cashierId: $user->id,
            items: [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 210]],
            payments: [], saleType: 'credit',
        ));

        $entry = JournalEntry::withoutTenantScope()->where('business_id', $businessId)->firstOrFail();
        $arLine = $entry->lines()->with('account')->get()->firstWhere('account.code', '1100');

        $this->assertNotNull($arLine);
        $this->assertSame('210.00', $arLine->debit);
    }

    public function test_a_goods_receipt_posts_inventory_against_accounts_payable(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Mutton', 'unit_id' => $unit->id]);
        $supplier = Supplier::create(['business_id' => $businessId, 'name' => 'Kalimati Traders']);
        $user = User::factory()->create(['business_id' => $businessId]);

        $order = app(PurchaseService::class)->createOrder(
            ['business_id' => $businessId, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id],
            [['business_id' => $businessId, 'product_id' => $product->id, 'ordered_qty' => 10, 'unit_cost' => 1050]],
        );
        app(PurchaseService::class)->receiveGoods($order, [
            ['purchase_order_item_id' => $order->items[0]->id, 'quantity' => 10, 'unit_cost' => 1050],
        ]);

        $entry = JournalEntry::withoutTenantScope()->where('business_id', $businessId)->firstOrFail();
        $lines = $entry->lines()->with('account')->get();

        $this->assertSame('10500.00', $lines->firstWhere('account.code', '1200')->debit);
        $this->assertSame('10500.00', $lines->firstWhere('account.code', '2000')->credit);
    }

    public function test_an_expense_posts_against_the_paid_via_account(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $category = ExpenseCategory::create(['business_id' => $businessId, 'name' => 'Utilities']);
        $user = User::factory()->create(['business_id' => $businessId]);

        app(ExpenseService::class)->record([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'expense_category_id' => $category->id,
            'amount' => 3200, 'paid_via' => 'bank', 'expense_date' => now()->toDateString(), 'created_by' => $user->id,
        ]);

        $entry = JournalEntry::withoutTenantScope()->where('business_id', $businessId)->firstOrFail();
        $lines = $entry->lines()->with('account')->get();

        $this->assertSame('3200.00', $lines->firstWhere('account.code', '6000')->debit);
        $this->assertSame('3200.00', $lines->firstWhere('account.code', '1010')->credit);
    }

    public function test_a_cash_session_shortage_posts_cash_over_short(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $businessId]);

        $service = app(CashSessionService::class);
        $session = $service->open($businessId, $branch->id, $terminal->id, $user->id, 1000);
        $service->close($session, 950, $user->id); // 50 short

        $entry = JournalEntry::withoutTenantScope()->where('business_id', $businessId)->firstOrFail();
        $lines = $entry->lines()->with('account')->get();

        $this->assertSame('50.00', $lines->firstWhere('account.code', '6100')->debit);
        $this->assertSame('50.00', $lines->firstWhere('account.code', '1000')->credit);
    }

    public function test_a_balanced_cash_session_posts_nothing(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);
        $user = User::factory()->create(['business_id' => $businessId]);

        $service = app(CashSessionService::class);
        $session = $service->open($businessId, $branch->id, $terminal->id, $user->id, 1000);
        $service->close($session, 1000, $user->id);

        $this->assertSame(0, JournalEntry::withoutTenantScope()->where('business_id', $businessId)->count());
    }
}
