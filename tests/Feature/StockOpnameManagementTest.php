<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use App\StockMovementType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_user_can_create_a_draft_with_a_system_stock_snapshot(): void
    {
        $this->travelTo('2026-10-10 09:00:00');
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => 'Preform 600ml']);
        StockBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1000,
        ]);

        $this->actingAs($warehouseUser)
            ->get(route('inventory.opnames.create'))
            ->assertOk()
            ->assertSeeText('Buat Stok Opname Bulanan')
            ->assertSeeText('Preform 600ml')
            ->assertSeeText('Stok Sistem')
            ->assertSeeText('Stok Fisik')
            ->assertSeeText('Selisih');

        $response = $this->actingAs($warehouseUser)->post(route('inventory.opnames.store'), [
            'period' => '2026-10',
            'opname_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'notes' => 'Penghitungan akhir periode',
            'items' => [
                ['product_id' => $product->id, 'physical_quantity' => 995, 'notes' => 'Rusak 5 pcs'],
            ],
        ]);

        $stockOpname = StockOpname::query()->firstOrFail();
        $response->assertRedirect(route('inventory.opnames.show', $stockOpname));
        $this->assertSame('draft', $stockOpname->status);
        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $product->id,
            'system_quantity' => 1000,
            'physical_quantity' => 995,
            'notes' => 'Rusak 5 pcs',
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $warehouseUser->id,
            'action' => 'CREATE',
            'module' => 'Stock Opname',
            'entity_id' => $stockOpname->id,
        ]);
    }

    public function test_administrator_posting_adjusts_each_difference_and_locks_the_opname(): void
    {
        $this->travelTo('2026-10-10 17:00:00');
        $administrator = User::factory()->administrator()->create();
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $shortProduct = Product::factory()->create(['name' => 'Robong Holo 600ml']);
        $overProduct = Product::factory()->create(['name' => 'Tutup Botol Biru']);
        StockBalance::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $shortProduct->id, 'quantity' => 100]);
        StockBalance::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $overProduct->id, 'quantity' => 20]);
        $stockOpname = StockOpname::factory()->create([
            'period' => '2026-10',
            'opname_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'created_by' => $warehouseUser->id,
        ]);
        $stockOpname->items()->createMany([
            ['product_id' => $shortProduct->id, 'system_quantity' => 100, 'physical_quantity' => 95, 'notes' => 'Kemasan rusak'],
            ['product_id' => $overProduct->id, 'system_quantity' => 20, 'physical_quantity' => 25, 'notes' => 'Temuan rak belakang'],
        ]);

        $response = $this->actingAs($administrator)->post(route('inventory.opnames.post', $stockOpname));

        $response->assertRedirect(route('inventory.opnames.show', $stockOpname));
        $this->assertSame('posted', $stockOpname->fresh()->status);
        $this->assertSame($administrator->id, $stockOpname->fresh()->posted_by);
        $this->assertSame('95.000', StockBalance::query()->where('product_id', $shortProduct->id)->value('quantity'));
        $this->assertSame('25.000', StockBalance::query()->where('product_id', $overProduct->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $shortProduct->id,
            'type' => StockMovementType::StockOpname->value,
            'direction' => 'out',
            'quantity' => 5,
            'source_type' => $stockOpname->getMorphClass(),
            'source_id' => $stockOpname->id,
            'reference_number' => $stockOpname->number,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $overProduct->id,
            'type' => StockMovementType::StockOpname->value,
            'direction' => 'in',
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'POST',
            'module' => 'Stock Opname',
            'entity_id' => $stockOpname->id,
        ]);
        $description = AuditLog::query()
            ->where('module', 'Stock Opname')
            ->where('action', 'POST')
            ->where('entity_id', $stockOpname->id)
            ->value('description');
        $this->assertStringContainsString('sistem 100,000', $description ?? '');
        $this->actingAs($administrator)->get(route('inventory.opnames.edit', $stockOpname))->assertNotFound();
    }

    public function test_updating_a_draft_refreshes_the_system_stock_snapshot(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stockOpname = StockOpname::factory()->create([
            'period' => '2026-10',
            'opname_date' => '2026-10-09',
            'warehouse_id' => $warehouse->id,
            'created_by' => $warehouseUser->id,
        ]);
        $item = $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_quantity' => 50,
            'physical_quantity' => 48,
        ]);
        StockBalance::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 75]);

        $response = $this->actingAs($warehouseUser)->put(route('inventory.opnames.update', $stockOpname), [
            'period' => '2026-10',
            'opname_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'notes' => 'Sudah dihitung ulang',
            'items' => [
                ['product_id' => $product->id, 'physical_quantity' => 49, 'notes' => 'Selisih satu'],
            ],
        ]);

        $response->assertRedirect(route('inventory.opnames.show', $stockOpname));
        $this->assertSame('75.000', $item->fresh()->system_quantity);
        $this->assertSame('49.000', $item->fresh()->physical_quantity);
        $this->assertSame('Selisih satu', $item->fresh()->notes);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_posting_rejects_a_draft_when_system_stock_changed_after_its_snapshot(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $balance = StockBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 90,
        ]);
        $stockOpname = StockOpname::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $administrator->id,
        ]);
        $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_quantity' => 100,
            'physical_quantity' => 90,
        ]);

        $response = $this->actingAs($administrator)
            ->from(route('inventory.opnames.show', $stockOpname))
            ->post(route('inventory.opnames.post', $stockOpname));

        $response->assertRedirect(route('inventory.opnames.show', $stockOpname));
        $response->assertSessionHasErrors([
            'stock' => 'Stok sistem berubah setelah draft dibuat. Edit draft, periksa kembali stok fisik, lalu simpan ulang sebelum posting.',
        ]);
        $this->assertSame('draft', $stockOpname->fresh()->status);
        $this->assertSame('90.000', $balance->fresh()->quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_refreshed_draft_can_be_posted_against_current_system_stock(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $balance = StockBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 90,
        ]);
        $stockOpname = StockOpname::factory()->create([
            'period' => '2026-10',
            'opname_date' => '2026-10-09',
            'warehouse_id' => $warehouse->id,
            'created_by' => $administrator->id,
        ]);
        $stockOpname->items()->create([
            'product_id' => $product->id,
            'system_quantity' => 100,
            'physical_quantity' => 90,
        ]);

        $this->actingAs($administrator)->put(route('inventory.opnames.update', $stockOpname), [
            'period' => '2026-10',
            'opname_date' => '2026-10-10',
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'physical_quantity' => 88],
            ],
        ])->assertRedirect(route('inventory.opnames.show', $stockOpname));

        $this->actingAs($administrator)
            ->post(route('inventory.opnames.post', $stockOpname))
            ->assertRedirect(route('inventory.opnames.show', $stockOpname));

        $this->assertSame('posted', $stockOpname->fresh()->status);
        $this->assertSame('88.000', $balance->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => StockMovementType::StockOpname->value,
            'direction' => 'out',
            'quantity' => 2,
        ]);
    }

    public function test_same_warehouse_cannot_have_two_opnames_for_the_same_period(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        StockOpname::factory()->create([
            'period' => '2026-10',
            'warehouse_id' => $warehouse->id,
            'created_by' => $warehouseUser->id,
        ]);

        $response = $this->actingAs($warehouseUser)
            ->from(route('inventory.opnames.create'))
            ->post(route('inventory.opnames.store'), [
                'period' => '2026-10',
                'opname_date' => '2026-10-10',
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'physical_quantity' => 0],
                ],
            ]);

        $response->assertRedirect(route('inventory.opnames.create'));
        $response->assertSessionHasErrors([
            'period' => 'Stok opname untuk gudang dan periode tersebut sudah tersedia.',
        ]);
        $this->assertDatabaseCount('stock_opnames', 1);
    }

    public function test_system_roles_can_post_while_inventory_roles_can_only_view(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $manager = User::factory()->create(['role' => UserRole::Owner]);
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $stockOpname = StockOpname::factory()->create(['created_by' => $warehouseUser->id]);

        $this->actingAs($warehouseUser)->get(route('inventory.opnames.index'))->assertOk();
        $this->actingAs($manager)->get(route('inventory.opnames.show', $stockOpname))->assertOk();
        $this->actingAs($warehouseUser)->post(route('inventory.opnames.post', $stockOpname))->assertForbidden();
        $this->actingAs($salesUser)->get(route('inventory.opnames.index'))->assertOk();
        $this->actingAs($salesUser)->post(route('inventory.opnames.post', $stockOpname))->assertForbidden();
        $this->actingAs($manager)->post(route('inventory.opnames.post', $stockOpname))->assertRedirect();
        $this->assertSame('posted', $stockOpname->fresh()->status);
    }

    public function test_single_active_warehouse_is_selected_automatically_with_its_current_stock(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => 'Botol 330ml']);
        StockBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 184648,
        ]);

        $response = $this->actingAs($warehouseUser)->get(route('inventory.opnames.create'));

        $response->assertOk()
            ->assertViewHas('defaultWarehouseId', $warehouse->id)
            ->assertSee('184.648,000');
    }

    public function test_multiple_active_warehouses_require_an_explicit_selection(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        Warehouse::factory()->count(2)->create(['is_active' => true]);
        Product::factory()->create();

        $response = $this->actingAs($warehouseUser)->get(route('inventory.opnames.create'));

        $response->assertOk()
            ->assertViewHas('defaultWarehouseId', null)
            ->assertSeeText('Pilih gudang');
    }
}
