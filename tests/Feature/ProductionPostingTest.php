<?php

namespace Tests\Feature;

use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\InventoryService;
use App\Services\ProductionService;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionPostingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_posting_daily_production_consumes_components_and_adds_finished_goods(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $component = Product::factory()->create(['name' => 'Preform', 'type' => ProductType::RawMaterial]);
        $finished = Product::factory()->create(['name' => 'Air 330 ml', 'type' => ProductType::FinishedGood]);
        ProductComposition::create(['product_id' => $finished->id, 'component_product_id' => $component->id, 'quantity' => '2']);
        app(InventoryService::class)->increase($component, $warehouse, '100', StockMovementType::OpeningBalance, $user);
        $production = DailyProduction::create(['number' => 'PROD-2026-000001', 'production_date' => today(), 'warehouse_id' => $warehouse->id, 'status' => 'draft', 'created_by' => $user->id]);
        $production->items()->create(['product_id' => $finished->id, 'quantity' => '10', 'rejected_quantity' => 0]);

        app(ProductionService::class)->post($production, $user);

        $this->assertSame('80.000', StockBalance::query()->where('product_id', $component->id)->value('quantity'));
        $this->assertSame('10.000', StockBalance::query()->where('product_id', $finished->id)->value('quantity'));
        $this->assertSame('posted', $production->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_posting_without_composition_shows_the_reason_and_does_not_change_stock(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $finished = Product::factory()->create(['name' => 'Robong Holo 330ml', 'type' => ProductType::FinishedGood]);
        $production = DailyProduction::create(['number' => 'PROD-2026-000002', 'production_date' => today(), 'warehouse_id' => $warehouse->id, 'status' => 'draft', 'created_by' => $user->id]);
        $production->items()->create(['product_id' => $finished->id, 'quantity' => '998', 'rejected_quantity' => '2']);

        $response = $this->actingAs($user)
            ->from(route('production.index'))
            ->followingRedirects()
            ->post(route('production.post', $production));

        $response->assertOk()
            ->assertSee('Transaksi belum dapat diproses.')
            ->assertSee('Komposisi Robong Holo 330ml belum diatur.');
        $this->assertSame('draft', $production->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_posting_same_production_twice_does_not_duplicate_stock_movements(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $component = Product::factory()->create(['type' => ProductType::RawMaterial]);
        $finished = Product::factory()->create(['type' => ProductType::FinishedGood]);
        ProductComposition::query()->create([
            'product_id' => $finished->id,
            'component_product_id' => $component->id,
            'quantity' => '1',
        ]);
        app(InventoryService::class)->increase($component, $warehouse, '10', StockMovementType::OpeningBalance, $user);
        $production = DailyProduction::query()->create([
            'number' => 'PROD-IDEMPOTENT-001',
            'production_date' => today(),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $production->items()->create(['product_id' => $finished->id, 'quantity' => '4', 'rejected_quantity' => 0]);

        app(ProductionService::class)->post($production, $user);

        try {
            app(ProductionService::class)->post($production, $user);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame('Hanya rekap draft yang dapat diposting.', $exception->errors()['status'][0]);
        }

        $this->assertSame('6.000', StockBalance::query()->whereBelongsTo($component)->whereBelongsTo($warehouse)->value('quantity'));
        $this->assertSame('4.000', StockBalance::query()->whereBelongsTo($finished)->whereBelongsTo($warehouse)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_multi_product_posting_rolls_back_every_stock_change_when_one_component_is_insufficient(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $availableComponent = Product::factory()->create(['type' => ProductType::RawMaterial]);
        $insufficientComponent = Product::factory()->create(['type' => ProductType::RawMaterial]);
        $firstFinishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $secondFinishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        ProductComposition::query()->create(['product_id' => $firstFinishedProduct->id, 'component_product_id' => $availableComponent->id, 'quantity' => '1']);
        ProductComposition::query()->create(['product_id' => $secondFinishedProduct->id, 'component_product_id' => $insufficientComponent->id, 'quantity' => '2']);
        app(InventoryService::class)->increase($availableComponent, $warehouse, '10', StockMovementType::OpeningBalance, $user);
        app(InventoryService::class)->increase($insufficientComponent, $warehouse, '1', StockMovementType::OpeningBalance, $user);
        $production = DailyProduction::query()->create([
            'number' => 'PROD-ATOMIC-001',
            'production_date' => today(),
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $production->items()->createMany([
            ['product_id' => $firstFinishedProduct->id, 'quantity' => '2', 'rejected_quantity' => 0],
            ['product_id' => $secondFinishedProduct->id, 'quantity' => '2', 'rejected_quantity' => 0],
        ]);

        try {
            app(ProductionService::class)->post($production, $user);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame('Stok tidak mencukupi untuk transaksi ini.', $exception->errors()['quantity'][0]);
        }

        $this->assertSame('draft', $production->fresh()->status);
        $this->assertSame('10.000', StockBalance::query()->whereBelongsTo($availableComponent)->whereBelongsTo($warehouse)->value('quantity'));
        $this->assertSame('1.000', StockBalance::query()->whereBelongsTo($insufficientComponent)->whereBelongsTo($warehouse)->value('quantity'));
        $this->assertDatabaseMissing('stock_balances', ['product_id' => $firstFinishedProduct->id, 'warehouse_id' => $warehouse->id]);
        $this->assertDatabaseMissing('stock_balances', ['product_id' => $secondFinishedProduct->id, 'warehouse_id' => $warehouse->id]);
        $this->assertDatabaseCount('stock_movements', 2);
    }
}
