<?php

namespace Modules\Notification\Providers;

use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Backup\Events\BackupCompleted;
use Modules\Notification\Listeners\SendBackupCompletedNotification;
use Modules\Notification\Listeners\SendFailedLoginAlert;
use Modules\Notification\Listeners\SendLargeDiscountWarning;
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
            SendLargeDiscountWarning::class,
        ],
        Failed::class => [
            SendFailedLoginAlert::class,
        ],
        BackupCompleted::class => [
            SendBackupCompletedNotification::class,
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
