<?php

namespace Modules\Backup\Console;

use Illuminate\Console\Command;
use Modules\Backup\Services\BackupService;

class RunBackupCommand extends Command
{
    protected $signature = 'backup:run {--trigger=scheduled : manual|scheduled}';

    protected $description = 'Back up the database and media into a single timestamped archive.';

    public function handle(BackupService $backups): int
    {
        $run = $backups->run($this->option('trigger'));

        if ($run->status === 'success') {
            $this->info("Backup complete: {$run->path} ({$run->sizeHuman()}).");

            return self::SUCCESS;
        }

        $this->error("Backup failed: {$run->failure_reason}");

        return self::FAILURE;
    }
}
