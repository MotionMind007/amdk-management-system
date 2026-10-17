<?php

namespace Tests\Feature;

use App\Services\DeploymentReadinessService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cached_configuration_is_reported_as_passing_instead_of_a_warning(): void
    {
        $this->app->instance('config_loaded_from_cache', true);

        $result = app(DeploymentReadinessService::class)->check();
        $optimization = collect($result['checks'])->firstWhere('name', 'Optimasi Laravel');

        $this->assertSame('pass', $optimization['status']);
        $this->assertSame('Konfigurasi sudah di-cache.', $optimization['message']);
    }

    public function test_local_configuration_is_rejected_for_production_deployment(): void
    {
        $this->artisan('system:check-deployment')
            ->expectsOutputToContain('APP_ENV harus production')
            ->assertFailed();
    }

    public function test_production_configuration_passes_readiness_check_on_postgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Kesiapan production diuji pada PostgreSQL.');
        }

        $this->app->detectEnvironment(fn (): string => 'production');
        Storage::fake('offsite-test');
        config([
            'app.debug' => false,
            'app.url' => 'https://staging.example.com',
            'app.timezone' => 'Asia/Jayapura',
            'cache.default' => 'database',
            'logging.default' => 'stderr',
            'logging.channels.stderr.level' => 'warning',
            'queue.default' => 'database',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'operations.database_backup.offsite_disk' => 'offsite-test',
        ]);
        $this->mock(ExecutableFinder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('find')->twice()->andReturn('/usr/bin/postgresql-client');
        });

        $this->artisan('system:check-deployment')
            ->expectsOutputToContain('Pemeriksaan deployment lulus')
            ->assertSuccessful();
    }

    public function test_production_readiness_rejects_missing_offsite_backup_storage(): void
    {
        config(['operations.database_backup.offsite_disk' => null]);

        $this->artisan('system:check-deployment')
            ->expectsOutputToContain('DB_BACKUP_OFFSITE_DISK harus menunjuk ke disk eksternal')
            ->assertFailed();
    }
}
