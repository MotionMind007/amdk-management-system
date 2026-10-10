<?php

namespace Tests\Feature;

use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_production_is_listed_with_an_edit_link(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $production = DailyProduction::create([
            'number' => 'PROD-2026-000001',
            'production_date' => '2026-10-08',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $production->items()->create([
            'product_id' => $product->id,
            'quantity' => 998,
            'rejected_quantity' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('production.index'));

        $response->assertSeeText('Edit');
        $response->assertSee(route('production.edit', $production), false);
    }

    public function test_administrator_can_update_a_draft_production(): void
    {
        $user = User::factory()->administrator()->create();
        $originalWarehouse = Warehouse::factory()->create();
        $newWarehouse = Warehouse::factory()->create();
        $originalProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $newProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $production = DailyProduction::create([
            'number' => 'PROD-2026-000001',
            'production_date' => '2026-10-07',
            'warehouse_id' => $originalWarehouse->id,
            'status' => 'draft',
            'notes' => null,
            'created_by' => $user->id,
        ]);
        $production->items()->create([
            'product_id' => $originalProduct->id,
            'quantity' => 998,
            'rejected_quantity' => 2,
        ]);

        $response = $this->actingAs($user)->put(route('production.update', $production), [
            'production_date' => '2026-10-08',
            'warehouse_id' => $newWarehouse->id,
            'notes' => 'Jumlah disesuaikan dengan stok bahan.',
            'items' => [
                [
                    'product_id' => $newProduct->id,
                    'quantity' => 416,
                    'rejected_quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('production.index'));
        $this->assertDatabaseHas('daily_productions', [
            'id' => $production->id,
            'production_date' => '2026-10-08 00:00:00',
            'warehouse_id' => $newWarehouse->id,
            'status' => 'draft',
            'notes' => 'Jumlah disesuaikan dengan stok bahan.',
        ]);
        $this->assertDatabaseMissing('daily_production_items', [
            'daily_production_id' => $production->id,
            'product_id' => $originalProduct->id,
        ]);
        $this->assertDatabaseHas('daily_production_items', [
            'daily_production_id' => $production->id,
            'product_id' => $newProduct->id,
            'quantity' => 416,
            'rejected_quantity' => 1,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'UPDATE',
            'module' => 'Production',
            'entity_id' => $production->id,
        ]);
    }

    public function test_edit_form_displays_existing_draft_values(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $production = DailyProduction::create([
            'number' => 'PROD-2026-000001',
            'production_date' => '2026-10-07',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'notes' => 'Draft yang perlu dikoreksi',
            'created_by' => $user->id,
        ]);
        $production->items()->create([
            'product_id' => $product->id,
            'quantity' => 998,
            'rejected_quantity' => 2,
        ]);

        $response = $this->actingAs($user)->get(route('production.edit', $production));

        $response->assertSeeText('Edit Rekap Produksi');
        $response->assertSeeText('Draft yang perlu dikoreksi');
        $response->assertSee('value="2026-10-07"', false);
        $response->assertSee('value="998.000"', false);
        $response->assertSee('value="2.000"', false);
        $response->assertSee('Simpan Perubahan');
    }

    public function test_posted_production_cannot_be_updated(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $production = DailyProduction::create([
            'number' => 'PROD-2026-000001',
            'production_date' => '2026-10-07',
            'warehouse_id' => $warehouse->id,
            'status' => 'posted',
            'notes' => 'Data final',
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $production->items()->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'rejected_quantity' => 0,
        ]);

        $response = $this->actingAs($user)
            ->from(route('production.index'))
            ->put(route('production.update', $production), [
                'production_date' => '2026-10-08',
                'warehouse_id' => $warehouse->id,
                'notes' => 'Mencoba mengubah data final',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 50,
                        'rejected_quantity' => 0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('production.index'));
        $response->assertSessionHasErrors([
            'status' => 'Hanya rekap produksi draft yang dapat diedit.',
        ]);
        $this->assertDatabaseHas('daily_productions', [
            'id' => $production->id,
            'production_date' => '2026-10-07 00:00:00',
            'notes' => 'Data final',
        ]);
        $this->assertDatabaseHas('daily_production_items', [
            'daily_production_id' => $production->id,
            'quantity' => 100,
        ]);
    }
}
