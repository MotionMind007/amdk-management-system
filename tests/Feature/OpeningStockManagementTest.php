<?php

namespace Tests\Feature;

use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\StockMovementType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpeningStockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_save_multiple_opening_stock_items_as_audited_draft(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();

        $this->actingAs($administrator)
            ->get(route('admin.opening-stocks.create'))
            ->assertOk()
            ->assertSeeText('Input Stok Awal')
            ->assertSeeText('Daftar Produk dan Bahan')
            ->assertSeeText('+ Tambah Baris');

        $response = $this->actingAs($administrator)->post(route('admin.opening-stocks.store'), [
            'stock_date' => '2026-10-08',
            'warehouse_id' => $warehouse->id,
            'notes' => 'Hasil stock opname manual',
            'items' => [
                ['product_id' => $firstProduct->id, 'quantity' => 120],
                ['product_id' => $secondProduct->id, 'quantity' => 45.5],
            ],
        ]);

        $openingStock = OpeningStock::query()->firstOrFail();
        $response->assertRedirect(route('admin.opening-stocks.index'));
        $this->assertSame('draft', $openingStock->status);
        $this->assertSame(2, $openingStock->items()->count());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE',
            'module' => 'Opening Stock',
            'entity_id' => $openingStock->id,
        ]);
    }

    public function test_posting_opening_stock_updates_balances_and_locks_the_document(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();
        $openingStock = OpeningStock::factory()->create([
            'stock_date' => '2026-10-01',
            'warehouse_id' => $warehouse->id,
            'created_by' => $administrator->id,
        ]);
        $openingStock->items()->createMany([
            ['product_id' => $firstProduct->id, 'quantity' => 100],
            ['product_id' => $secondProduct->id, 'quantity' => 25.5],
        ]);

        $response = $this->actingAs($administrator)->post(route('admin.opening-stocks.post', $openingStock));

        $response->assertRedirect();
        $this->assertSame('posted', $openingStock->fresh()->status);
        $this->assertSame($administrator->id, $openingStock->fresh()->posted_by);
        $this->assertSame('100.000', StockBalance::query()->where('product_id', $firstProduct->id)->value('quantity'));
        $this->assertSame('25.500', StockBalance::query()->where('product_id', $secondProduct->id)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $firstProduct->id,
            'warehouse_id' => $warehouse->id,
            'type' => StockMovementType::OpeningBalance->value,
            'direction' => 'in',
            'source_type' => $openingStock->getMorphClass(),
            'source_id' => $openingStock->id,
            'reference_number' => $openingStock->number,
            'occurred_at' => '2026-10-01 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'POST',
            'module' => 'Opening Stock',
            'entity_id' => $openingStock->id,
        ]);
        $this->actingAs($administrator)->get(route('admin.opening-stocks.edit', $openingStock))->assertNotFound();

        $secondPost = $this->actingAs($administrator)
            ->from(route('admin.opening-stocks.index'))
            ->post(route('admin.opening-stocks.post', $openingStock));
        $secondPost->assertRedirect(route('admin.opening-stocks.index'));
        $secondPost->assertSessionHasErrors([
            'status' => 'Stok awal ini sudah diposting dan tidak dapat diproses kembali.',
        ]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_administrator_can_update_a_draft_before_posting(): void
    {
        $administrator = User::factory()->administrator()->create();
        $originalWarehouse = Warehouse::factory()->create();
        $newWarehouse = Warehouse::factory()->create();
        $originalProduct = Product::factory()->create();
        $newProduct = Product::factory()->create();
        $openingStock = OpeningStock::factory()->create([
            'warehouse_id' => $originalWarehouse->id,
            'created_by' => $administrator->id,
        ]);
        $openingStock->items()->create(['product_id' => $originalProduct->id, 'quantity' => 10]);

        $response = $this->actingAs($administrator)->put(route('admin.opening-stocks.update', $openingStock), [
            'stock_date' => '2026-10-07',
            'warehouse_id' => $newWarehouse->id,
            'notes' => 'Hasil hitung ulang',
            'items' => [
                ['product_id' => $newProduct->id, 'quantity' => 25],
            ],
        ]);

        $response->assertRedirect(route('admin.opening-stocks.index'));
        $this->assertDatabaseHas('opening_stocks', [
            'id' => $openingStock->id,
            'stock_date' => '2026-10-07 00:00:00',
            'warehouse_id' => $newWarehouse->id,
            'status' => 'draft',
            'notes' => 'Hasil hitung ulang',
        ]);
        $this->assertDatabaseMissing('opening_stock_items', [
            'opening_stock_id' => $openingStock->id,
            'product_id' => $originalProduct->id,
        ]);
        $this->assertDatabaseHas('opening_stock_items', [
            'opening_stock_id' => $openingStock->id,
            'product_id' => $newProduct->id,
            'quantity' => 25,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_opening_stock_cannot_be_posted_when_an_item_already_has_a_stock_movement(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => 'Botol Kosong 600ml']);
        app(InventoryService::class)->increase($product, $warehouse, '5', StockMovementType::Adjustment, $administrator);
        $openingStock = OpeningStock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $administrator->id,
        ]);
        $openingStock->items()->create(['product_id' => $product->id, 'quantity' => 100]);

        $response = $this->actingAs($administrator)
            ->from(route('admin.opening-stocks.index'))
            ->post(route('admin.opening-stocks.post', $openingStock));

        $response->assertRedirect(route('admin.opening-stocks.index'));
        $response->assertSessionHasErrors([
            'stock' => 'Stok awal Botol Kosong 600ml tidak dapat diposting karena sudah memiliki pergerakan stok di gudang ini.',
        ]);
        $this->assertSame('draft', $openingStock->fresh()->status);
        $this->assertSame('5.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_duplicate_products_are_rejected_without_creating_a_draft(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($administrator)
            ->from(route('admin.opening-stocks.create'))
            ->post(route('admin.opening-stocks.store'), [
                'stock_date' => '2026-10-08',
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                    ['product_id' => $product->id, 'quantity' => 20],
                ],
            ]);

        $response->assertRedirect(route('admin.opening-stocks.create'));
        $response->assertSessionHasErrors([
            'items.1.product_id' => 'Produk atau bahan tidak boleh sama.',
        ]);
        $this->assertDatabaseCount('opening_stocks', 0);
    }

    public function test_soft_deleted_product_cannot_be_added_to_opening_stock(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->actingAs($administrator)
            ->from(route('admin.opening-stocks.create'))
            ->post(route('admin.opening-stocks.store'), [
                'stock_date' => '2026-10-08',
                'warehouse_id' => $warehouse->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 10],
                ],
            ]);

        $response->assertRedirect(route('admin.opening-stocks.create'));
        $response->assertSessionHasErrors('items.0.product_id');
        $this->assertDatabaseCount('opening_stocks', 0);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_roles_cannot_access_opening_stock(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('admin.opening-stocks.index'))->assertForbidden();
    }

    /** @return array<string, array{UserRole}> */
    public static function nonAdministratorRoles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            if (! in_array($role, [UserRole::SuperAdministrator, UserRole::Owner], true)) {
                $roles[$role->value] = [$role];
            }
        }

        return $roles;
    }
}
