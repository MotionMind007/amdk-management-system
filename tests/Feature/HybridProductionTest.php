<?php

namespace Tests\Feature;

use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductionType;
use App\ProductType;
use App\Services\InventoryService;
use App\Services\ProductionService;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HybridProductionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_production_form_offers_automatic_finished_goods_and_manual_packaging_modes(): void
    {
        $user = User::factory()->administrator()->create();
        Product::factory()->create(['name' => 'Air Mineral 600ml', 'type' => ProductType::FinishedGood]);
        Product::factory()->create(['name' => 'Botol Kosong 600ml', 'type' => ProductType::PackagingMaterial]);
        Product::factory()->create(['name' => 'Preform 16 gram', 'type' => ProductType::RawMaterial]);

        $response = $this->actingAs($user)->get(route('production.create'));

        $response->assertOk()
            ->assertSee('Produk Jadi')
            ->assertSee('Kemasan / Blowing')
            ->assertSee('Air Mineral 600ml')
            ->assertSee('Botol Kosong 600ml')
            ->assertSee('Preform 16 gram')
            ->assertSee('Bahan Aktual Terpakai');
    }

    public function test_user_can_save_packaging_production_with_multiple_outputs_and_actual_materials(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $bottle330 = Product::factory()->create(['type' => ProductType::PackagingMaterial]);
        $bottle600 = Product::factory()->create(['type' => ProductType::PackagingMaterial]);
        $preform = Product::factory()->create(['type' => ProductType::RawMaterial]);

        $response = $this->actingAs($user)->post(route('production.store'), [
            'production_type' => ProductionType::Packaging->value,
            'production_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $bottle330->id, 'quantity' => 1000, 'rejected_quantity' => 20],
                ['product_id' => $bottle600->id, 'quantity' => 500, 'rejected_quantity' => 10],
            ],
            'materials' => [
                ['product_id' => $preform->id, 'quantity' => 1530],
            ],
        ]);

        $response->assertRedirect(route('production.index'));
        $production = DailyProduction::query()->firstOrFail();

        $this->assertSame(ProductionType::Packaging, $production->production_type);
        $this->assertCount(2, $production->items);
        $this->assertDatabaseHas('daily_production_items', [
            'daily_production_id' => $production->id,
            'product_id' => $bottle330->id,
            'quantity' => 1000,
            'rejected_quantity' => 20,
        ]);
        $this->assertDatabaseHas('daily_production_materials', [
            'daily_production_id' => $production->id,
            'product_id' => $preform->id,
            'quantity' => 1530,
        ]);
    }

    public function test_posting_packaging_production_uses_actual_material_and_adds_only_good_output(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $preform = Product::factory()->create(['name' => 'Preform 16 gram', 'type' => ProductType::RawMaterial]);
        $bottle = Product::factory()->create(['name' => 'Botol 330ml', 'type' => ProductType::PackagingMaterial]);
        app(InventoryService::class)->increase($preform, $warehouse, '1100', StockMovementType::OpeningBalance, $user);
        $production = DailyProduction::create([
            'number' => 'PROD-2026-000100',
            'production_date' => today(),
            'warehouse_id' => $warehouse->id,
            'production_type' => ProductionType::Packaging,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $production->items()->create([
            'product_id' => $bottle->id,
            'quantity' => 1000,
            'rejected_quantity' => 20,
        ]);
        $production->materials()->create([
            'product_id' => $preform->id,
            'quantity' => 1020,
        ]);

        app(ProductionService::class)->post($production, $user);

        $this->assertSame('80.000', StockBalance::query()->where('product_id', $preform->id)->value('quantity'));
        $this->assertSame('1000.000', StockBalance::query()->where('product_id', $bottle->id)->value('quantity'));
        $this->assertSame('posted', $production->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $preform->id,
            'direction' => 'out',
            'quantity' => 1020,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $bottle->id,
            'direction' => 'in',
            'quantity' => 1000,
        ]);
    }

    public function test_packaging_output_cannot_also_be_used_as_material(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $bottle = Product::factory()->create(['type' => ProductType::PackagingMaterial]);

        $response = $this->actingAs($user)
            ->from(route('production.create'))
            ->post(route('production.store'), [
                'production_type' => ProductionType::Packaging->value,
                'production_date' => '2026-10-10',
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $bottle->id, 'quantity' => 1000, 'rejected_quantity' => 20],
                ],
                'materials' => [
                    ['product_id' => $bottle->id, 'quantity' => 1020],
                ],
            ]);

        $response->assertRedirect(route('production.create'))
            ->assertSessionHasErrors([
                'materials' => 'Produk hasil tidak boleh digunakan sebagai bahan pada rekap yang sama.',
            ]);
        $this->assertDatabaseCount('daily_productions', 0);
    }

    public function test_production_rejects_inactive_warehouse_output_and_material(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['is_active' => false]);
        $bottle = Product::factory()->create([
            'type' => ProductType::PackagingMaterial,
            'is_active' => false,
        ]);
        $preform = Product::factory()->create([
            'type' => ProductType::RawMaterial,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('production.create'))
            ->post(route('production.store'), [
                'production_type' => ProductionType::Packaging->value,
                'production_date' => '2026-10-10',
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $bottle->id, 'quantity' => 1000],
                ],
                'materials' => [
                    ['product_id' => $preform->id, 'quantity' => 1000],
                ],
            ]);

        $response->assertRedirect(route('production.create'));
        $response->assertSessionHasErrors(['warehouse_id', 'items.0.product_id', 'materials.0.product_id']);
        $this->assertDatabaseCount('daily_productions', 0);
    }

    public function test_operational_report_counts_only_finished_good_production(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $finishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $packagingProduct = Product::factory()->create(['type' => ProductType::PackagingMaterial]);

        $finishedProduction = DailyProduction::create([
            'number' => 'PROD-FINISHED',
            'production_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'production_type' => ProductionType::FinishedGood,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $finishedProduction->items()->create(['product_id' => $finishedProduct->id, 'quantity' => 500, 'rejected_quantity' => 0]);

        $packagingProduction = DailyProduction::create([
            'number' => 'PROD-PACKAGING',
            'production_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'production_type' => ProductionType::Packaging,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $packagingProduction->items()->create(['product_id' => $packagingProduct->id, 'quantity' => 12000, 'rejected_quantity' => 50]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-10',
        ]));

        $response->assertOk();
        $this->assertSame(500.0, (float) $response->viewData('productionTotal'));
    }
}
