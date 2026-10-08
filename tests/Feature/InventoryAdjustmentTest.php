<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_user_can_adjust_stock_with_audit_trail(): void
    {
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

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
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
