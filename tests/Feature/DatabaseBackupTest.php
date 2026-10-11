<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_create_and_download_private_database_backup(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->post(route('admin.backups.store'))->assertRedirect()->assertSessionHas('success');
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE', Storage::disk('local')->get($files[0]));
        $this->assertStringContainsString('CREATE INDEX', Storage::disk('local')->get($files[0]));
        $this->actingAs($owner)->get(route('admin.backups.download', basename($files[0])))->assertDownload();
        $this->assertDatabaseHas('audit_logs', ['action' => 'BACKUP', 'module' => 'System']);
    }

    public function test_admin_cannot_access_database_backups(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('admin.backups.index'))->assertForbidden();
    }
}
