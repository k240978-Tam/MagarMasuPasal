<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Support\ReportPeriod;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Financial reports are selected by Nepali month, fiscal year, or an explicit
 * AD range, and every one of them exports to CSV — this covers the resolution
 * rules and that each export actually streams the right period.
 */
class ReportPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_nepali_month_resolves_to_its_ad_range(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['bs_month' => '2083-04']));

        $this->assertSame('Shrawan 2083', $period->label);
        $this->assertSame('2026-07-17', $period->from->toDateString());
        $this->assertSame('2026-08-16', $period->to->toDateString());
        $this->assertSame(['bs_month' => '2083-04'], $period->queryParameters());
    }

    public function test_a_fiscal_year_resolves_to_shrawan_through_ashadh(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['fiscal_year' => '2083/84']));

        $this->assertSame('Fiscal Year 2083/84', $period->label);
        $this->assertSame('2026-07-17', $period->from->toDateString());
        $this->assertSame('2027-07-16', $period->to->toDateString());
    }

    public function test_an_explicit_ad_range_is_honoured(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'from' => '2026-04-01',
            'to' => '2026-06-30',
        ]));

        $this->assertSame('2026-04-01', $period->from->toDateString());
        $this->assertSame('2026-06-30', $period->to->toDateString());
        $this->assertSame(['from' => '2026-04-01', 'to' => '2026-06-30'], $period->queryParameters());
    }

    public function test_an_out_of_range_nepali_month_falls_back_instead_of_erroring(): void
    {
        // BS 2150 is far outside the calendar data; a hand-edited URL must not
        // produce an error page for the shop owner.
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['bs_month' => '2150-04']));

        $this->assertNotNull($period->bsMonth);
        $this->assertNotSame('2150-04', $period->bsMonth);
    }

    /**
     * @dataProvider reportNames
     */
    public function test_every_financial_report_exports_a_csv_stamped_with_its_period(string $report): void
    {
        $owner = $this->owner();

        $response = $this->actingAs($owner)
            ->get("/accounting/reports/{$report}/export?bs_month=2083-04");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Shrawan 2083', $csv);
        $this->assertStringContainsString('2083-04-01 to 2083-04-31', $csv);
        $this->assertStringContainsString('2026-07-17 to 2026-08-16', $csv);
    }

    /**
     * @return array<int, array{string}>
     */
    public static function reportNames(): array
    {
        return [
            ['profit-loss'],
            ['trial-balance'],
            ['balance-sheet'],
            ['cash-book'],
            ['ledger'],
        ];
    }

    public function test_report_pages_render_for_a_selected_nepali_month(): void
    {
        $owner = $this->owner();

        foreach (['profit-loss', 'trial-balance', 'balance-sheet', 'cash-book', 'ledger'] as $report) {
            $this->actingAs($owner)
                ->get("/accounting/reports/{$report}?bs_month=2083-04")
                ->assertOk()
                ->assertSee('Shrawan 2083');
        }
    }

    private function owner(): User
    {
        $branch = BranchFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($branch->business_id);

        Permission::findOrCreate('reports.view', 'web');
        $role = Role::create(['name' => 'Report Viewer', 'guard_name' => 'web', 'business_id' => null]);
        $role->givePermissionTo('reports.view');

        $user = User::factory()->create([
            'business_id' => $branch->business_id,
            'default_branch_id' => $branch->id,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
