<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\Branch;
use Tests\TestCase;

/**
 * Proves the single enforcement point for tenant isolation described in
 * docs/architecture/01-system-architecture.md §1.4: with a TenantContext
 * resolved, every tenant-scoped query is automatically confined to that
 * business — no module has to remember to add a business_id filter itself.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_scoped_to_the_current_tenant(): void
    {
        $branchA = BranchFactory::new()->create(['name' => 'Halchowk']);
        $branchB = BranchFactory::new()->create(['name' => 'Baneshwor']);

        $context = app(TenantContext::class);

        $context->setBusinessId($branchA->business_id);
        $this->assertCount(1, Branch::all());
        $this->assertSame('Halchowk', Branch::first()->name);

        $context->setBusinessId($branchB->business_id);
        $this->assertCount(1, Branch::all());
        $this->assertSame('Baneshwor', Branch::first()->name);
    }

    public function test_without_tenant_scope_sees_every_tenant(): void
    {
        BranchFactory::new()->create();
        BranchFactory::new()->create();

        app(TenantContext::class)->setBusinessId(999999);

        $this->assertCount(0, Branch::all());
        $this->assertCount(2, Branch::withoutTenantScope()->get());
    }

    public function test_new_records_are_stamped_with_the_current_tenant(): void
    {
        $branch = BranchFactory::new()->create();

        app(TenantContext::class)->setBusinessId($branch->business_id);

        $created = Branch::create([
            'name' => 'New Branch',
            'status' => 'active',
        ]);

        $this->assertSame($branch->business_id, $created->business_id);
    }
}
