<?php

namespace Modules\Backup\Console;

use Illuminate\Console\Command;
use Modules\Backup\Models\BackupRun;
use Modules\Backup\Services\BackupService;

/**
 * A backup that "succeeded" but whose archive later got corrupted or
 * deleted is worse than an obvious failure — silent until restore day.
 * This re-checks the most recent successful run and flips it to failed
 * if it no longer holds up, so monitoring catches it immediately.
 */
class VerifyBackupsCommand extends Command
{
    protected $signature = 'backup:verify';

    protected $description = "Verify the most recent successful backup's archive is still intact.";

    public function handle(BackupService $backups): int
    {
        $latest = BackupRun::where('status', 'success')->latest('finished_at')->first();

        if (! $latest) {
            $this->warn('No successful backup to verify yet.');

            return self::SUCCESS;
        }

        if ($backups->verify($latest)) {
            $this->info("Backup #{$latest->id} verified OK.");

            return self::SUCCESS;
        }

        $latest->update(['status' => 'failed', 'failure_reason' => 'Failed integrity check on verification.']);
        $this->error("Backup #{$latest->id} FAILED verification — archive missing, empty, or corrupt.");

        return self::FAILURE;
    }
}
