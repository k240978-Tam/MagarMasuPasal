<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Modules\Tenancy\Models\Branch;
use Modules\Units\Models\Unit;
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

    /**
     * Unit predates BelongsToTenant's strict-equality model — null business_id
     * means "shared platform default" here, not "belongs to no one" — so it
     * needs its own scope. Found while writing the Phase 6 tenant-isolation
     * sweep: Unit had a business_id column but no scope enforcing it at all,
     * meaning a plain `Unit::all()` leaked every tenant's custom units to
     * every other tenant.
     */
    public function test_units_shows_shared_defaults_plus_only_the_current_tenants_own_custom_units(): void
    {
        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        Unit::create(['business_id' => null, 'name' => 'Kilogram', 'symbol' => 'kg']);
        Unit::create(['business_id' => $branchA->business_id, 'name' => "A's Crate", 'symbol' => 'crate-a']);
        Unit::create(['business_id' => $branchB->business_id, 'name' => "B's Sack", 'symbol' => 'sack-b']);

        app(TenantContext::class)->setBusinessId($branchA->business_id);

        $visible = Unit::pluck('symbol')->all();

        $this->assertContains('kg', $visible, 'Shared platform defaults must remain visible.');
        $this->assertContains('crate-a', $visible, 'Tenant A must see its own custom unit.');
        $this->assertNotContains('sack-b', $visible, "Tenant A must never see tenant B's custom unit.");
    }
}
