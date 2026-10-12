<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        ]);
        $this->mock(ExecutableFinder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('find')->twice()->andReturn('/usr/bin/postgresql-client');
        });

        $this->artisan('system:check-deployment')
            ->expectsOutputToContain('Pemeriksaan deployment lulus')
            ->assertSuccessful();
    }
}
