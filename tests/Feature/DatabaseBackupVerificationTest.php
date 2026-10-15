<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\DatabaseBackupVerifier;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sqlite_backup_can_be_restored_and_verified_in_isolation(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restore SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        User::factory()->count(2)->create();
        $usersBeforeVerification = User::count();

        $backup = app(DatabaseBackupService::class)->create();
        $result = app(DatabaseBackupVerifier::class)->verify($backup['filename']);

        $this->assertSame($backup['filename'], $result['filename']);
        $this->assertSame('sqlite', $result['driver']);
        $this->assertSame(64, strlen($result['checksum']));
        $this->assertGreaterThan(0, $result['tables']);
        $this->assertGreaterThanOrEqual($usersBeforeVerification, $result['rows']);
        $this->assertSame($usersBeforeVerification, User::count());
        $this->assertEqualsCanonicalizing([
            'backups/'.$backup['filename'],
            'backups/'.$backup['filename'].'.verified.json',
        ], Storage::disk('local')->allFiles('backups'));
        $this->assertSame($result['verified_at'], app(DatabaseBackupService::class)->all()[0]['verified_at']);
    }

    public function test_corrupt_sqlite_backup_fails_verification_and_removes_temporary_database(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restore SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        $filename = 'database-20261012-010000-corrupt.sql';
        Storage::disk('local')->put('backups/'.$filename, 'BUKAN SQL YANG VALID');

        try {
            app(DatabaseBackupVerifier::class)->verify($filename);
            $this->fail('Backup rusak seharusnya gagal diverifikasi.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $this->assertSame(['backups/'.$filename], Storage::disk('local')->allFiles('backups'));
    }

    public function test_verify_backup_command_uses_latest_backup(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restore SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        $backup = app(DatabaseBackupService::class)->create();

        $this->artisan('system:verify-backup')
            ->expectsOutputToContain($backup['filename'])
            ->expectsOutputToContain('Database aktif tidak diubah')
            ->assertSuccessful();
    }

    public function test_owner_sees_verified_restore_status_but_cannot_download_metadata(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restore SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $backup = app(DatabaseBackupService::class)->create();
        app(DatabaseBackupVerifier::class)->verify($backup['filename']);

        $this->actingAs($owner)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertSee('Restore terverifikasi')
            ->assertSee('php artisan system:verify-backup');
        $this->actingAs($owner)
            ->get(route('admin.backups.download', $backup['filename'].'.verified.json'))
            ->assertNotFound();
    }

    public function test_verified_backup_and_metadata_are_replicated_offsite(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Restore SQLite hanya diuji pada koneksi SQLite.');
        }

        $this->fakeLocalStorage();
        Storage::fake('offsite-test');
        config(['operations.database_backup.offsite_disk' => 'offsite-test']);
        $backup = app(DatabaseBackupService::class)->create();

        app(DatabaseBackupVerifier::class)->verify($backup['filename']);

        Storage::disk('offsite-test')->assertExists('backups/'.$backup['filename']);
        Storage::disk('offsite-test')->assertExists('backups/'.$backup['filename'].'.verified.json');
    }
}
