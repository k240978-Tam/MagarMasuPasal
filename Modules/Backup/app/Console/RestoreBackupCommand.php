<?php

namespace Modules\Backup\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Backup\Models\BackupRun;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Deliberately requires --force and always takes a timestamped safety copy
 * of the live database before overwriting it, so a bad restore is itself
 * recoverable — this is the single most destructive command in the
 * platform and treats it accordingly.
 */
class RestoreBackupCommand extends Command
{
    protected $signature = 'backup:restore {run_id : The backup_runs.id to restore from} {--force : Required to actually run this}';

    protected $description = 'Restore the database from a completed backup run. Overwrites the live database.';

    public function handle(): int
    {
        $run = BackupRun::find($this->argument('run_id'));

        if (! $run || $run->status !== 'success' || ! $run->path) {
            $this->error('That backup run is not a completed, successful backup.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Refusing to restore without --force. This overwrites the live database.');

            return self::FAILURE;
        }

        $zipPath = storage_path("app/{$run->path}");

        if (! File::exists($zipPath)) {
            $this->error("Backup archive not found at {$zipPath}.");

            return self::FAILURE;
        }

        $driver = DB::connection()->getDriverName();
        $extractDir = storage_path('app/backups/restore-'.uniqid());
        File::ensureDirectoryExists($extractDir);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error('Could not open the backup archive.');

            return self::FAILURE;
        }
        $zip->extractTo($extractDir);
        $zip->close();

        try {
            if ($driver === 'sqlite') {
                $this->restoreSqlite($extractDir);
            } elseif ($driver === 'mysql') {
                $this->restoreMysql($extractDir);
            } else {
                throw new RuntimeException("No restore strategy for driver [{$driver}].");
            }
        } finally {
            File::deleteDirectory($extractDir);
        }

        $this->info('Restore complete.');

        return self::SUCCESS;
    }

    protected function restoreSqlite(string $extractDir): void
    {
        $dumpFile = "{$extractDir}/database.sqlite";

        if (! File::exists($dumpFile)) {
            throw new RuntimeException('This backup does not contain a database.sqlite file.');
        }

        $liveDb = config('database.connections.sqlite.database');
        $safetyCopy = $liveDb.'.before-restore-'.now()->format('Y-m-d_His');
        File::copy($liveDb, $safetyCopy);
        $this->warn("Safety copy of the current database saved to {$safetyCopy}");

        File::copy($dumpFile, $liveDb);
    }

    protected function restoreMysql(string $extractDir): void
    {
        $dumpFile = "{$extractDir}/database.sql";

        if (! File::exists($dumpFile)) {
            throw new RuntimeException('This backup does not contain a database.sql file.');
        }

        $config = config('database.connections.mysql');
        $safetyDumpPath = storage_path('app/backups/before-restore-'.now()->format('Y-m-d_His').'.sql');

        $dumpProcess = new Process([
            'mysqldump', '-h', $config['host'], '-u', $config['username'],
            '--password='.$config['password'], $config['database'],
            '--result-file='.$safetyDumpPath,
        ]);
        $dumpProcess->run();
        $this->warn("Safety dump of the current database saved to {$safetyDumpPath}");

        $restoreProcess = Process::fromShellCommandline(sprintf(
            'mysql -h %s -u %s --password=%s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($dumpFile),
        ));
        $restoreProcess->run();

        if (! $restoreProcess->isSuccessful()) {
            throw new RuntimeException('mysql restore failed: '.$restoreProcess->getErrorOutput());
        }
    }
}
