<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\OperationalIntegrityService;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OperationalIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_integrity_check_passes_for_an_empty_consistent_database(): void
    {
        $result = app(OperationalIntegrityService::class)->check();

        $this->assertTrue($result['healthy']);
        $this->artisan('system:check-integrity')->assertExitCode(Command::SUCCESS);
    }

    public function test_integrity_check_detects_a_stock_balance_without_matching_movements(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        StockBalance::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

        $result = app(OperationalIntegrityService::class)->check();

        $this->assertFalse($result['healthy']);
        $this->assertSame(1, $result['checks']['stock']['issues']);
        $this->artisan('system:check-integrity')->assertExitCode(Command::FAILURE);
    }
}
