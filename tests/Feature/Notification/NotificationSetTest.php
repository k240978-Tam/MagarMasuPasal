<?php

namespace Tests\Feature\Notification;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Inventory\Models\InventoryStock;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Notifications\CustomerDueAlert;
use Modules\Notification\Notifications\DailySummaryDigest;
use Modules\Notification\Notifications\FailedLoginAlert;
use Modules\Notification\Notifications\LargeDiscountWarning;
use Modules\Notification\Notifications\LowStockAlert;
use Modules\Notification\Notifications\SupplierDueAlert;
use Modules\Products\Models\Product;
use Modules\Sales\Events\SaleCompleted;
use Modules\Sales\Models\Sale;
use Modules\Sales\Models\SaleItem;
use Modules\Suppliers\Models\Supplier;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\BranchTerminal;
use Modules\Units\Models\Unit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationSetTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwner(int $businessId, int $branchId): User
    {
        $owner = User::factory()->create(['business_id' => $businessId, 'default_branch_id' => $branchId]);
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'business_id' => null]);
        $owner->assignRole($role);

        return $owner;
    }

    public function test_low_stock_command_notifies_management_only_when_below_threshold(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480, 'min_stock' => 10]);
        InventoryStock::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'product_id' => $product->id, 'quantity_on_hand' => 5]);

        Artisan::call('notifications:low-stock');

        NotificationFacade::assertSentTo($owner, LowStockAlert::class);
    }

    public function test_daily_summary_command_reports_hand_computed_totals(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);
        app(ChartOfAccountsService::class)->seedDefaults($businessId);

        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);
        $product = Product::create(['business_id' => $businessId, 'name' => 'Chicken', 'unit_id' => $unit->id, 'selling_price' => 480]);
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        $yesterday = now()->subDay()->toDateString();
        $sale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
            'cashier_id' => $owner->id, 'invoice_no' => 'INV-000001', 'subtotal' => 500, 'discount_amount' => 0,
            'tax_amount' => 0, 'total_amount' => 500, 'status' => 'completed', 'sale_type' => 'retail',
            'completed_at' => $yesterday.' 10:00:00',
        ]);
        SaleItem::create(['business_id' => $businessId, 'sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500]);

        Artisan::call('notifications:daily-summary', ['date' => $yesterday]);

        NotificationFacade::assertSentTo($owner, DailySummaryDigest::class, function ($notification) {
            $data = $notification->toArray($notification);

            return $data['total_sales'] === 500.0 && $data['transaction_count'] === 1;
        });
    }

    public function test_due_alerts_notify_for_customers_near_limit_and_suppliers_over_threshold(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);

        $group = CustomerGroup::create(['business_id' => $businessId, 'name' => 'Credit', 'allow_credit' => true, 'credit_limit' => 1000]);
        Customer::create(['business_id' => $businessId, 'customer_group_id' => $group->id, 'name' => 'Hotel Everest', 'current_due' => 950]);

        Supplier::create(['business_id' => $businessId, 'name' => 'Kalimati Traders', 'current_due' => 20000]);

        Artisan::call('notifications:dues');

        NotificationFacade::assertSentTo($owner, CustomerDueAlert::class);
        NotificationFacade::assertSentTo($owner, SupplierDueAlert::class);
    }

    public function test_a_large_discount_notifies_management_but_a_small_one_does_not(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);
        app(ChartOfAccountsService::class)->seedDefaults($businessId);
        $terminal = BranchTerminal::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'name' => 'Counter 1']);

        $bigDiscountSale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
            'cashier_id' => $owner->id, 'invoice_no' => 'INV-000001', 'subtotal' => 1000,
            'discount_amount' => 300, 'tax_amount' => 0, 'total_amount' => 700,
            'status' => 'completed', 'sale_type' => 'retail', 'completed_at' => now(),
        ]);

        event(new SaleCompleted($bigDiscountSale));

        NotificationFacade::assertSentTo($owner, LargeDiscountWarning::class);

        NotificationFacade::fake();

        $smallDiscountSale = Sale::create([
            'business_id' => $businessId, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id,
            'cashier_id' => $owner->id, 'invoice_no' => 'INV-000002', 'subtotal' => 1000,
            'discount_amount' => 50, 'tax_amount' => 0, 'total_amount' => 950,
            'status' => 'completed', 'sale_type' => 'retail', 'completed_at' => now(),
        ]);

        event(new SaleCompleted($smallDiscountSale));

        NotificationFacade::assertNotSentTo($owner, LargeDiscountWarning::class);
    }

    public function test_a_failed_login_for_a_known_user_alerts_their_business_but_an_unknown_email_does_not(): void
    {
        NotificationFacade::fake();

        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);
        $attemptedUser = User::factory()->create(['business_id' => $businessId, 'email' => 'cashier@test.com']);

        event(new Failed('web', $attemptedUser, ['email' => 'cashier@test.com', 'password' => 'wrong']));

        NotificationFacade::assertSentTo($owner, FailedLoginAlert::class);

        NotificationFacade::fake();
        event(new Failed('web', null, ['email' => 'nobody@nowhere.test', 'password' => 'wrong']));

        NotificationFacade::assertNothingSent();
    }

    public function test_a_disabled_channel_preference_is_respected(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        $owner = $this->makeOwner($businessId, $branch->id);

        NotificationPreference::create([
            'business_id' => $businessId, 'user_id' => $owner->id,
            'channel' => 'mail', 'type' => 'low_stock', 'enabled' => false,
        ]);

        $notification = new LowStockAlert('Halchowk', [['product' => 'Chicken', 'branch' => 'Halchowk', 'on_hand' => 2, 'threshold' => 10]]);
        $channels = $notification->via($owner);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }
}
