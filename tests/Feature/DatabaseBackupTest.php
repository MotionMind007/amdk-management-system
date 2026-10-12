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
        Storage::fake('local');
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

        Storage::fake('local');

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
}
