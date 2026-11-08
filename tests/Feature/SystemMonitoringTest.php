<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemMonitoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_missing_backup_sends_one_alert_to_active_owner_and_super_administrator(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $superAdministrator = User::factory()->create(['role' => UserRole::SuperAdministrator]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Storage::fake('local');
        Storage::fake('s3');
        config(['operations.database_backup.offsite_disk' => 's3']);

        $this->artisan('system:monitor-health')->assertFailed();
        $this->artisan('system:monitor-health')->assertFailed();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $superAdministrator->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame('Peringatan Sistem', $owner->notifications()->firstOrFail()->data['title']);
        $this->assertStringContainsString('Backup Database', $owner->notifications()->firstOrFail()->data['message']);
    }

    public function test_verified_backup_in_r2_keeps_system_monitoring_healthy(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $filename = 'database-20261108-010000-test.sql';
        $content = 'backup database';
        Storage::fake('local');
        Storage::fake('s3');
        Storage::disk('local')->put('backups/'.$filename, $content);
        Storage::disk('local')->put('backups/'.$filename.'.verified.json', json_encode([
            'checksum' => hash('sha256', $content),
            'verified_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
        Storage::disk('s3')->put('backups/'.$filename, $content);
        config(['operations.database_backup.offsite_disk' => 's3']);

        $this->artisan('system:monitor-health')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_restored_backup_state_sends_recovery_notification(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $filename = 'database-20261108-010000-recovered.sql';
        $content = 'backup database';
        Storage::fake('local');
        Storage::fake('s3');
        config(['operations.database_backup.offsite_disk' => 's3']);

        $this->artisan('system:monitor-health')->assertFailed();

        Storage::disk('local')->put('backups/'.$filename, $content);
        Storage::disk('local')->put('backups/'.$filename.'.verified.json', json_encode([
            'checksum' => hash('sha256', $content),
            'verified_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR));
        Storage::disk('s3')->put('backups/'.$filename, $content);

        $this->artisan('system:monitor-health')->assertSuccessful();

        $this->assertSame(2, $owner->notifications()->count());
        $recoveryNotification = $owner->notifications()->get()->first(
            fn (DatabaseNotification $notification): bool => $notification->data['title'] === 'Pemantauan Sistem Pulih',
        );

        $this->assertNotNull($recoveryNotification);
        $this->assertSame('Pemantauan Sistem Pulih', $recoveryNotification->data['title']);
    }
}
