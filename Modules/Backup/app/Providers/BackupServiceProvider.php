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
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(RunBackupCommand::class)->dailyAt('02:00');
        $schedule->command(VerifyBackupsCommand::class)->dailyAt('02:30');
    }
}
