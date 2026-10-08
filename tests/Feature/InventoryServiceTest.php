<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_increase_updates_balance_and_creates_movement_atomically(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $movement = app(InventoryService::class)->increase(
            $product,
            $warehouse,
            '125',
            StockMovementType::OpeningBalance,
            referenceNumber: 'OPEN-001',
        );

        $this->assertSame('125.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('125.000', $movement->balance_after);
        $this->assertSame('in', $movement->direction);
        $this->assertSame('OPEN-001', $movement->reference_number);
    }

    public function test_decrease_is_rejected_when_stock_is_insufficient_without_partial_change(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        app(InventoryService::class)->increase($product, $warehouse, '10', StockMovementType::OpeningBalance);

        try {
            app(InventoryService::class)->decrease($product, $warehouse, '11', StockMovementType::Sale);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame('Stok tidak mencukupi untuk transaksi ini.', $exception->errors()['quantity'][0]);
        }

        $this->assertSame('10.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
