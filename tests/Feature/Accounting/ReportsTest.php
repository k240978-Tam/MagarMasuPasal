<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Services\AccountingReportService;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Tenancy\Database\Factories\BranchFactory;
use Tests\TestCase;

/**
 * Proves the reports reconcile against hand-computed numbers — the roadmap's
 * "manually-verified test data" bar for Phase 3 — including the case that
 * broke during manual testing: an account sitting in an abnormal balance
 * (overdrawn cash) must still show up, on the correct side, in the trial
 * balance, or the report silently drops money.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_totals_match_even_with_an_overdrawn_account(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);
        $user = User::factory()->create(['business_id' => $businessId]);
        $journal = app(JournalEntryService::class);

        // Cash starts with nothing, then pays out 500 -> ends up net credit
        // (overdrawn), which is the abnormal-balance case that must still
        // appear in the trial balance, on the credit side.
        $journal->post($businessId, $branch->id, 'Owner draw', now()->toDateString(), [
            ['account_code' => ChartOfAccountsService::OWNERS_EQUITY, 'debit' => 500],
            ['account_code' => ChartOfAccountsService::CASH, 'credit' => 500],
        ], createdBy: $user->id);

        $tb = app(AccountingReportService::class)->trialBalance($businessId);

        $cashRow = $tb->firstWhere('code', ChartOfAccountsService::CASH);
        $this->assertNotNull($cashRow, 'Overdrawn Cash account must still appear in the trial balance.');
        $this->assertSame(0.0, $cashRow['debit']);
        $this->assertSame(500.0, $cashRow['credit']);

        $this->assertEqualsWithDelta($tb->sum('debit'), $tb->sum('credit'), 0.01);
    }

    public function test_profit_and_loss_respects_the_date_range_including_the_boundary_day(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);
        $user = User::factory()->create(['business_id' => $businessId]);
        $journal = app(JournalEntryService::class);
        $today = now()->toDateString();

        $journal->post($businessId, $branch->id, 'Sale today', $today, [
            ['account_code' => ChartOfAccountsService::CASH, 'debit' => 1000],
            ['account_code' => ChartOfAccountsService::SALES_REVENUE, 'credit' => 1000],
        ], createdBy: $user->id);

        $pnl = app(AccountingReportService::class)->profitAndLoss($businessId, $today, $today);

        $this->assertSame(1000.0, $pnl['income'], 'An entry dated exactly on the "to" boundary must be included.');
    }

    public function test_balance_sheet_balances(): void
    {
        $branch = BranchFactory::new()->create();
        $businessId = $branch->business_id;
        app(ChartOfAccountsService::class)->seedDefaults($businessId);
        $user = User::factory()->create(['business_id' => $businessId]);
        $journal = app(JournalEntryService::class);

        $journal->post($businessId, $branch->id, 'Opening capital', now()->toDateString(), [
            ['account_code' => ChartOfAccountsService::BANK, 'debit' => 50000],
            ['account_code' => ChartOfAccountsService::OWNERS_EQUITY, 'credit' => 50000],
        ], createdBy: $user->id);

        $journal->post($businessId, $branch->id, 'A sale', now()->toDateString(), [
            ['account_code' => ChartOfAccountsService::CASH, 'debit' => 1000],
            ['account_code' => ChartOfAccountsService::SALES_REVENUE, 'credit' => 1000],
        ], createdBy: $user->id);

        $bs = app(AccountingReportService::class)->balanceSheet($businessId, now()->toDateString());

        $this->assertEqualsWithDelta(
            $bs['assets'],
            $bs['liabilities'] + $bs['equity'] + $bs['retained_earnings'],
            0.01,
        );
    }
}
