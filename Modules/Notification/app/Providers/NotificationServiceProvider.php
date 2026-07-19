<?php

namespace Modules\Notification\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Notification\Console\SendDailySummaryCommand;
use Modules\Notification\Console\SendDueAlertsCommand;
use Modules\Notification\Console\SendLowStockAlertsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NotificationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Notification';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'notification';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendLowStockAlertsCommand::class,
        SendDailySummaryCommand::class,
        SendDueAlertsCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(SendLowStockAlertsCommand::class)->dailyAt('08:00');
        $schedule->command(SendDailySummaryCommand::class)->dailyAt('07:00');
        $schedule->command(SendDueAlertsCommand::class)->dailyAt('08:15');
    }
}
