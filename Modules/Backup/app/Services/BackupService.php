<?php

namespace Modules\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\Backup\Events\BackupCompleted;
use Modules\Backup\Models\BackupRun;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Self-contained backup: no external CLI dependency beyond the driver's own
 * dump tool (mysqldump for MySQL — SQLite needs nothing, since a SQLite
 * database *is* just a file, so "dumping" it is a straight copy). Everything
 * lands in one timestamped zip: the database plus any uploaded media, so a
 * single file is the entire restore artifact.
 */
class BackupService
{
    protected string $disk = 'local';

    public function run(string $trigger = 'manual', ?int $triggeredBy = null): BackupRun
    {
        $run = BackupRun::create([
            'status' => 'running',
            'trigger' => $trigger,
            'disk' => $this->disk,
            'triggered_by' => $triggeredBy,
            'started_at' => now(),
        ]);

        try {
            $path = $this->buildArchive();

            $run->update([
                'status' => 'success',
                'path' => $path,
                'size_bytes' => File::size(storage_path("app/{$path}")),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        BackupCompleted::dispatch($run->fresh());

        return $run->fresh();
    }

    /**
     * Confirms a completed backup's archive still exists, is non-empty, and
     * opens as a valid zip — the "did the backup actually work" check the
     * roadmap calls out separately from just running one.
     */
    public function verify(BackupRun $run): bool
    {
        if ($run->status !== 'success' || ! $run->path) {
            return false;
        }

        $fullPath = storage_path("app/{$run->path}");

        if (! File::exists($fullPath) || File::size($fullPath) === 0) {
            return false;
        }

        $zip = new ZipArchive;

        return $zip->open($fullPath, ZipArchive::CHECKCONS) === true && $zip->close() !== false;
    }

    protected function buildArchive(): string
    {
        $relativePath = 'backups/backup-'.now()->format('Y-m-d_His').'.zip';
        $fullPath = storage_path("app/{$relativePath}");
        File::ensureDirectoryExists(dirname($fullPath));

        $zip = new ZipArchive;

        if ($zip->open($fullPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Could not create backup archive at {$fullPath}.");
        }

        $tempFiles = [$this->addDatabaseDump($zip)];
        $this->addMedia($zip);

        // ZipArchive defers writing file contents until close() — any temp
        // dump file must survive until after this call, or the entry ends
        // up empty in the archive.
        $zip->close();

        foreach (array_filter($tempFiles) as $tempFile) {
            File::delete($tempFile);
        }

        return $relativePath;
    }

    /**
     * @return string|null the temp dump file path to clean up after the
     *                     zip is closed, or null when nothing was written to disk (SQLite).
     */
    protected function addDatabaseDump(ZipArchive $zip): ?string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');

            if ($dbPath === ':memory:' || ! File::exists($dbPath)) {
                throw new RuntimeException("SQLite database file not found at [{$dbPath}] — nothing to back up.");
            }

            $zip->addFile($dbPath, 'database.sqlite');

            return null;
        }

        if ($driver === 'mysql') {
            $config = config('database.connections.mysql');
            $dumpPath = storage_path('app/backups/tmp-dump-'.uniqid().'.sql');
            File::ensureDirectoryExists(dirname($dumpPath));

            $process = new Process([
                'mysqldump',
                '-h', $config['host'],
                '-u', $config['username'],
                '--password='.$config['password'],
                $config['database'],
                '--result-file='.$dumpPath,
            ]);
            $process->run();

            if (! $process->isSuccessful() || ! File::exists($dumpPath)) {
                throw new RuntimeException('mysqldump failed: '.$process->getErrorOutput());
            }

            $zip->addFile($dumpPath, 'database.sql');

            return $dumpPath;
        }

        throw new RuntimeException("No dump strategy configured for database driver [{$driver}].");
    }

    protected function addMedia(ZipArchive $zip): void
    {
        $mediaPath = storage_path('app/public');

        if (! File::isDirectory($mediaPath)) {
            return;
        }

        foreach (File::allFiles($mediaPath) as $file) {
            $zip->addFile($file->getPathname(), 'media/'.$file->getRelativePathname());
        }
    }
}
