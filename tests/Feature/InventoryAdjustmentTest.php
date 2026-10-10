<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_can_adjust_stock_with_audit_trail(): void
    {
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create(['name' => 'Preform 16 gram']);
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Utama']);

        $response = $this->actingAs($user)->post(route('inventory.adjustment.store'), [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'quantity' => 12,
            'notes' => 'Hasil stock opname',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $this->assertSame('12.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'adjustment', 'direction' => 'in']);
        $auditLog = AuditLog::query()->sole();
        $this->assertStringContainsString('Penambahan stok Preform 16 gram sebanyak 12,000', $auditLog->description);
        $this->assertStringContainsString('di Gudang Utama', $auditLog->description);
        $this->assertStringContainsString('Alasan: Hasil stock opname', $auditLog->description);
        $this->assertStringContainsString('Saldo 0,000 menjadi 12,000', $auditLog->description);

        $this->actingAs($user)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Penambahan stok Preform 16 gram')
            ->assertSeeText('Hasil stock opname');
    }

    public function test_adjustment_rejects_inactive_product_and_warehouse(): void
    {
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create(['is_active' => false]);
        $warehouse = Warehouse::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)
            ->from(route('inventory.adjustment.create'))
            ->post(route('inventory.adjustment.store'), [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'direction' => 'in',
                'quantity' => 12,
                'notes' => 'Koreksi stok',
            ]);

        $response->assertRedirect(route('inventory.adjustment.create'));
        $response->assertSessionHasErrors(['product_id', 'warehouse_id']);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_inventory_service_rechecks_master_status_before_moving_stock(): void
    {
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        Product::query()->whereKey($product)->update(['is_active' => false]);

        try {
            app(InventoryService::class)->increase(
                $product,
                $warehouse,
                '12',
                StockMovementType::Adjustment,
                $user,
            );
            $this->fail('Pergerakan stok seharusnya ditolak untuk master produk yang sudah tidak aktif.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Produk atau bahan tidak aktif atau tidak tersedia.'], $exception->errors()['product_id']);
        }

        $this->assertDatabaseCount('stock_balances', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
