<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Backup\Events\BackupCompleted;
use Modules\Backup\Models\BackupRun;
use Modules\Backup\Services\BackupService;
use Modules\Notification\Notifications\BackupCompletedAlert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Proves the backup archive is real (a valid, non-empty zip containing the
 * live SQLite database), that verification actually detects a corrupted
 * archive rather than trusting the DB row, and that BackupCompleted reaches
 * every Owner regardless of which business they belong to.
 *
 * Deliberately does NOT use RefreshDatabase: that trait wraps every test in
 * a transaction, and a file-backed SQLite database is what BackupService
 * needs to actually copy — a real file, not the suite's usual :memory:
 * connection, which has nothing on disk to back up in the first place.
 */
class BackupServiceTest extends TestCase
{
    protected string $tempDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDbPath = storage_path('app/backups/test-db-'.uniqid().'.sqlite');
        File::ensureDirectoryExists(dirname($this->tempDbPath));
        File::put($this->tempDbPath, '');

        config(['database.connections.sqlite.database' => $this->tempDbPath]);
        DB::purge('sqlite');

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups'));
        File::delete($this->tempDbPath);

        // Restore the suite's default :memory: connection so later test
        // classes' RefreshDatabase setup isn't left pointed at a deleted file.
        config(['database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_run_produces_a_valid_zip_containing_the_database(): void
    {
        $run = app(BackupService::class)->run('manual');

        $this->assertSame('success', $run->status);
        $this->assertNotNull($run->path);
        $this->assertGreaterThan(0, $run->size_bytes);

        $fullPath = storage_path("app/{$run->path}");
        $this->assertFileExists($fullPath);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($fullPath) === true);
        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $zip->close();
    }

    public function test_verify_detects_a_deleted_archive(): void
    {
        $run = app(BackupService::class)->run('manual');
        $this->assertTrue(app(BackupService::class)->verify($run));

        File::delete(storage_path("app/{$run->path}"));

        $this->assertFalse(app(BackupService::class)->verify($run));
    }

    public function test_verify_command_flips_a_corrupted_backup_to_failed(): void
    {
        $run = app(BackupService::class)->run('manual');
        File::put(storage_path("app/{$run->path}"), 'not a zip file');

        Artisan::call('backup:verify');

        $this->assertSame('failed', $run->fresh()->status);
    }

    public function test_backup_completed_notifies_every_owner_regardless_of_business(): void
    {
        NotificationFacade::fake();

        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'business_id' => null]);
        $ownerA = User::factory()->create();
        $ownerA->assignRole($role);
        $ownerB = User::factory()->create();
        $ownerB->assignRole($role);
        $nonOwner = User::factory()->create();

        $run = BackupRun::create(['status' => 'success', 'trigger' => 'manual', 'started_at' => now(), 'finished_at' => now()]);
        event(new BackupCompleted($run));

        NotificationFacade::assertSentTo($ownerA, BackupCompletedAlert::class);
        NotificationFacade::assertSentTo($ownerB, BackupCompletedAlert::class);
        NotificationFacade::assertNotSentTo($nonOwner, BackupCompletedAlert::class);
    }
}
