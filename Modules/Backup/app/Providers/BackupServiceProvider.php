<?php

namespace Modules\Backup\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Backup\Console\RestoreBackupCommand;
use Modules\Backup\Console\RunBackupCommand;
use Modules\Backup\Console\VerifyBackupsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BackupServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Backup';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'backup';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RunBackupCommand::class,
        VerifyBackupsCommand::class,
        RestoreBackupCommand::class,
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
     * Explicit Asia/Kathmandu times: config('app.timezone') stays UTC (the
     * server's own clock), and every business on the platform today is in
     * Nepal, so an un-anchored dailyAt() would land in the middle of the
     * shop's business hours instead of overnight. Found during the Phase 8
     * launch review.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(RunBackupCommand::class)
            ->dailyAt('01:00')->timezone('Asia/Kathmandu')
            ->withoutOverlapping()->onOneServer();

        $schedule->command(VerifyBackupsCommand::class)
            ->dailyAt('01:15')->timezone('Asia/Kathmandu')
            ->withoutOverlapping()->onOneServer();
    }
}
