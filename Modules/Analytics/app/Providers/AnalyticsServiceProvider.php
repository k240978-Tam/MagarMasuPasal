<?php

namespace Modules\Analytics\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Analytics\Console\AggregateDailySalesCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AnalyticsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Analytics';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'analytics';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        AggregateDailySalesCommand::class,
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
    /**
     * Explicit Asia/Kathmandu time — see BackupServiceProvider for why an
     * un-anchored dailyAt() is wrong here (config('app.timezone') is UTC,
     * not the tenant's own timezone).
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(AggregateDailySalesCommand::class)
            ->dailyAt('01:30')->timezone('Asia/Kathmandu')
            ->withoutOverlapping()->onOneServer();
    }
}
