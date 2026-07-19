<?php

namespace Modules\Accounting\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Accounting\Listeners\PostCashSessionVarianceJournalEntry;
use Modules\Accounting\Listeners\PostExpenseJournalEntry;
use Modules\Accounting\Listeners\PostPurchaseJournalEntry;
use Modules\Accounting\Listeners\PostSaleJournalEntry;
use Modules\CashRegister\Events\CashSessionClosed;
use Modules\Expenses\Events\ExpenseRecorded;
use Modules\Purchases\Events\PurchaseBatchReceived;
use Modules\Sales\Events\SaleCompleted;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        SaleCompleted::class => [
            PostSaleJournalEntry::class,
        ],
        PurchaseBatchReceived::class => [
            PostPurchaseJournalEntry::class,
        ],
        ExpenseRecorded::class => [
            PostExpenseJournalEntry::class,
        ],
        CashSessionClosed::class => [
            PostCashSessionVarianceJournalEntry::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
