<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_and_download_private_database_backup(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $filename = 'database-20261012-010000-test.sql';
        $this->mock(DatabaseBackupService::class, function (MockInterface $mock) use ($filename): void {
            $mock->expects('create')->once()->andReturnUsing(function () use ($filename): array {
                Storage::disk('local')->put('backups/'.$filename, 'test backup');

                return ['filename' => $filename, 'size' => 11, 'created_at' => now()->timestamp];
            });
        });

        $this->actingAs($owner)->post(route('admin.backups.store'))->assertRedirect()->assertSessionHas('success');
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->actingAs($owner)->get(route('admin.backups.download', basename($files[0])))->assertDownload();
        $this->assertDatabaseHas('audit_logs', ['action' => 'BACKUP', 'module' => 'System']);
    }

    public function test_sqlite_backup_contains_schema_and_indexes(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dump SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();

        $backup = app(DatabaseBackupService::class)->create();
        $contents = Storage::disk('local')->get('backups/'.$backup['filename']);

        $this->assertStringContainsString('CREATE TABLE', $contents);
        $this->assertStringContainsString('CREATE INDEX', $contents);
    }

    public function test_admin_cannot_access_database_backups(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertForbidden();
    }

    public function test_backup_is_replicated_to_configured_offsite_disk(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dump SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        Storage::fake('offsite-test');
        config(['operations.database_backup.offsite_disk' => 'offsite-test']);

        $backup = app(DatabaseBackupService::class)->create();

        Storage::disk('local')->assertExists('backups/'.$backup['filename']);
        Storage::disk('offsite-test')->assertExists('backups/'.$backup['filename']);
        $this->assertSame('offsite-test', $backup['offsite_disk']);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSeeText('Tersalin ke offsite-test');
    }

    public function test_scheduled_backup_command_creates_local_and_offsite_copies(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Dump SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        Storage::fake('offsite-test');
        config(['operations.database_backup.offsite_disk' => 'offsite-test']);

        $this->artisan('system:backup-database', ['--verify' => true, '--retention-days' => 30])
            ->expectsOutputToContain('berhasil dibuat')
            ->expectsOutputToContain('berhasil disalin ke disk offsite offsite-test')
            ->expectsOutputToContain('Restore backup terverifikasi')
            ->expectsOutputToContain('Retensi 30 hari diterapkan')
            ->assertSuccessful();

        $this->assertCount(2, Storage::disk('local')->files('backups'));
        $this->assertCount(2, Storage::disk('offsite-test')->files('backups'));
    }

    public function test_backup_retention_removes_old_dump_and_verification_metadata_from_both_disks(): void
    {
        $this->fakeLocalStorage();
        Storage::fake('offsite-test');
        config(['operations.database_backup.offsite_disk' => 'offsite-test']);
        $oldPath = 'backups/database-20260801-010000-old.sql';
        $recentPath = 'backups/database-20261016-010000-recent.sql';

        foreach (['local', 'offsite-test'] as $disk) {
            Storage::disk($disk)->put($oldPath, 'old backup');
            Storage::disk($disk)->put($oldPath.'.verified.json', '{}');
            Storage::disk($disk)->put($recentPath, 'recent backup');
        }
        touch(Storage::disk('local')->path($oldPath), now()->subDays(31)->timestamp);

        $pruned = app(DatabaseBackupService::class)->pruneOlderThan(30);

        $this->assertSame(1, $pruned);
        foreach (['local', 'offsite-test'] as $disk) {
            Storage::disk($disk)->assertMissing($oldPath);
            Storage::disk($disk)->assertMissing($oldPath.'.verified.json');
            Storage::disk($disk)->assertExists($recentPath);
        }
    }
}
