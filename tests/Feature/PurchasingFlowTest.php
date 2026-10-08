<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchasingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PurchasingFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_po_does_not_change_stock_and_partial_receipt_adds_only_received_quantity(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::create(['number' => 'PO-2026-000001', 'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'order_date' => today(), 'status' => 'approved', 'total' => '50000', 'created_by' => $user->id]);
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => '100', 'received_quantity' => 0, 'unit_price' => '500', 'line_total' => '50000']);

        $this->assertDatabaseCount('stock_balances', 0);
        $receipt = app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '80'], $user);

        $this->assertSame('80.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('40000.00', $purchaseOrder->fresh()->outstanding_amount);
        $this->assertSame('partially_received', $purchaseOrder->fresh()->status);
        $this->assertSame('40000.00', $receipt->total);
    }
}
