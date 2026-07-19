<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerGroup;
use Modules\Notification\Notifications\LowStockAlert;
use Modules\Products\Models\Product;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Units\Models\Unit;
use Tests\TestCase;

class CoreApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function tokenFor(User $user): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'test-suite',
        ])->json('data.token');
    }

    public function test_dashboard_summary_is_reachable_and_scoped(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure(['data' => ['todays_sales', 'transaction_count', 'scope']]);
    }

    public function test_products_index_and_barcode_lookup_are_tenant_scoped(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();
        $unit = Unit::create(['name' => 'Kilogram', 'symbol' => 'kg', 'conversion_factor' => 1]);

        Product::create(['business_id' => $branchA->business_id, 'name' => "A's Chicken", 'unit_id' => $unit->id, 'selling_price' => 480, 'barcode' => 'AAA111']);
        Product::create(['business_id' => $branchB->business_id, 'name' => "B's Chicken", 'unit_id' => $unit->id, 'selling_price' => 480, 'barcode' => 'BBB222']);

        $userA = User::factory()->create(['business_id' => $branchA->business_id, 'password' => 'password']);
        $token = $this->tokenFor($userA);

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/products')->json('data');
        $this->assertCount(1, $list);
        $this->assertSame("A's Chicken", $list[0]['name']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products/barcode/AAA111')
            ->assertOk()
            ->assertJsonPath('data.name', "A's Chicken");

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products/barcode/BBB222')
            ->assertNotFound();
    }

    public function test_customers_index_and_dues_are_tenant_scoped(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        $groupA = CustomerGroup::create(['business_id' => $branchA->business_id, 'name' => 'Credit', 'allow_credit' => true, 'credit_limit' => 5000]);
        $customerA = Customer::create(['business_id' => $branchA->business_id, 'customer_group_id' => $groupA->id, 'name' => 'Hotel Everest', 'current_due' => 1000]);

        $groupB = CustomerGroup::create(['business_id' => $branchB->business_id, 'name' => 'Credit', 'allow_credit' => true, 'credit_limit' => 5000]);
        Customer::create(['business_id' => $branchB->business_id, 'customer_group_id' => $groupB->id, 'name' => 'Other Business Customer']);

        $userA = User::factory()->create(['business_id' => $branchA->business_id, 'password' => 'password']);
        $token = $this->tokenFor($userA);

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/customers')->json('data');
        $this->assertCount(1, $list);
        $this->assertSame('Hotel Everest', $list[0]['name']);

        $dues = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/customers/{$customerA->public_id}/dues")
            ->assertOk()
            ->json('data');

        $this->assertEquals(1000.0, $dues['current_due']);
        $this->assertEquals(4000.0, $dues['credit_available']);
    }

    public function test_notifications_index_and_mark_read(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['business_id' => $branch->business_id, 'password' => 'password']);
        $user->notify(new LowStockAlert('Halchowk', [['product' => 'Chicken', 'branch' => 'Halchowk', 'on_hand' => 2, 'threshold' => 10]]));

        $token = $this->tokenFor($user);

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/notifications')->json('data');
        $this->assertCount(1, $list);
        $this->assertNull($list[0]['read_at']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/notifications/{$list[0]['id']}/read")
            ->assertOk();

        $refreshed = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/notifications')->json('data');
        $this->assertNotNull($refreshed[0]['read_at']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }
}
