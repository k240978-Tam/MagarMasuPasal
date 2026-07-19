<?php

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Accounting\Services\ChartOfAccountsService;
use Modules\Accounting\Services\JournalEntryService;
use Modules\Tenancy\Database\Factories\BusinessFactory;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_balanced_entry_posts_successfully(): void
    {
        $business = BusinessFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $entry = app(JournalEntryService::class)->post(
            businessId: $business->id,
            branchId: null,
            description: 'Test entry',
            entryDate: now()->toDateString(),
            lines: [
                ['account_code' => ChartOfAccountsService::CASH, 'debit' => 500],
                ['account_code' => ChartOfAccountsService::SALES_REVENUE, 'credit' => 500],
            ],
        );

        $this->assertSame('posted', $entry->status);
        $this->assertCount(2, $entry->lines);
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        $business = BusinessFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $this->expectException(InvalidArgumentException::class);

        app(JournalEntryService::class)->post(
            businessId: $business->id,
            branchId: null,
            description: 'Broken entry',
            entryDate: now()->toDateString(),
            lines: [
                ['account_code' => ChartOfAccountsService::CASH, 'debit' => 500],
                ['account_code' => ChartOfAccountsService::SALES_REVENUE, 'credit' => 400],
            ],
        );
    }

    public function test_unknown_account_code_fails_loudly(): void
    {
        $business = BusinessFactory::new()->create();
        app(ChartOfAccountsService::class)->seedDefaults($business->id);

        $this->expectException(\RuntimeException::class);

        app(JournalEntryService::class)->post(
            businessId: $business->id,
            branchId: null,
            description: 'Bad code',
            entryDate: now()->toDateString(),
            lines: [
                ['account_code' => '9999', 'debit' => 100],
                ['account_code' => ChartOfAccountsService::CASH, 'credit' => 100],
            ],
        );
    }
}
