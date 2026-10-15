<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Services\OpeningStockService;
use App\Services\OperationalIntegrityService;
use App\UserRole;
use Database\Seeders\UatSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class UatSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uat_seeder_creates_repeatable_consistent_non_production_data(): void
    {
        config()->set('operations.uat.password', 'UatPassword!123');

        $this->app->make(UatSeeder::class)->run($this->app->make(OpeningStockService::class));
        $this->seed(UatSeeder::class);

        foreach (UserRole::cases() as $role) {
            $this->assertDatabaseHas('users', [
                'email' => match ($role) {
                    UserRole::Owner => 'uat.owner@amdk.local',
                    UserRole::SuperAdministrator => 'uat.superadmin@amdk.local',
                    UserRole::Admin => 'uat.admin@amdk.local',
                    UserRole::Warehouse => 'uat.gudang@amdk.local',
                    UserRole::Sales => 'uat.sales@amdk.local',
                },
                'role' => $role->value,
                'is_active' => true,
            ]);
        }

        $this->assertSame(11, Product::query()->where('sku', 'like', 'UAT-%')->count());
        $this->assertSame(8, ProductComposition::query()->count());
        $this->assertSame(1, OpeningStock::query()->where('number', 'UAT-STOK-AWAL-001')->where('status', 'posted')->count());
        $this->assertSame(11, OpeningStock::query()->where('number', 'UAT-STOK-AWAL-001')->firstOrFail()->items()->count());
        $this->assertSame(2, CashAccount::query()->where('code', 'like', 'UAT-%')->count());
        $this->assertSame(2, CashTransaction::query()->where('reference_number', 'like', 'UAT-SALDO-AWAL-%')->count());
        $this->assertSame('25000000.00', CashAccount::query()->where('code', 'UAT-KAS')->value('balance'));
        $this->assertSame('50000000.00', CashAccount::query()->where('code', 'UAT-BANK')->value('balance'));

        $integrity = app(OperationalIntegrityService::class)->check();
        $this->assertTrue($integrity['healthy'], json_encode($integrity, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function test_uat_seeder_requires_a_password(): void
    {
        config()->set('operations.uat.password');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Atur UAT_PASSWORD minimal 12 karakter');

        $this->seed(UatSeeder::class);
    }

    public function test_uat_seeder_cannot_run_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set('operations.uat.password', 'UatPassword!123');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak boleh dijalankan di lingkungan production');

        $this->app->make(UatSeeder::class)->run($this->app->make(OpeningStockService::class));
    }
}
