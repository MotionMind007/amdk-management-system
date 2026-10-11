<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\InventoryService;
use App\Services\SalesService;
use App\StockMovementType;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeliveryNoteFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_posting_sale_does_not_reduce_stock_until_warehouse_posts_delivery_note(): void
    {
        [$sale, $product, $warehouse, $salesUser] = $this->makePostedSale();
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);

        $this->assertSame('20.000', StockBalance::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame('pending', $sale->fresh()->delivery_status);

        $response = $this->actingAs($warehouseUser)->post(route('sales.deliveries.store', $sale), [
            'delivery_date' => '2026-10-11',
            'notes' => 'Diterima bagian gudang pelanggan',
            'quantities' => [$sale->items()->firstOrFail()->id => 3],
        ]);

        $deliveryNote = $sale->deliveryNotes()->firstOrFail();
        $response->assertRedirect(route('sales.deliveries.index'));
        $this->assertSame('17.000', StockBalance::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame('partial', $sale->fresh()->delivery_status);
        $this->assertDatabaseHas('stock_movements', ['source_type' => $deliveryNote->getMorphClass(), 'source_id' => $deliveryNote->id, 'direction' => 'out']);
        $this->assertDatabaseHas('audit_logs', ['module' => 'DeliveryNote', 'entity_id' => $deliveryNote->id]);
    }

    public function test_sales_role_cannot_post_delivery_note(): void
    {
        [$sale] = $this->makePostedSale();
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($salesUser)
            ->post(route('sales.deliveries.store', $sale), [
                'delivery_date' => today()->format('Y-m-d'),
                'quantities' => [$sale->items()->firstOrFail()->id => 1],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('delivery_notes', 0);
    }

    public function test_delivery_cannot_exceed_undelivered_quantity(): void
    {
        [$sale] = $this->makePostedSale();
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);

        $response = $this->actingAs($warehouseUser)
            ->from(route('sales.deliveries.create', $sale))
            ->post(route('sales.deliveries.store', $sale), [
                'delivery_date' => today()->format('Y-m-d'),
                'quantities' => [$sale->items()->firstOrFail()->id => 6],
            ]);

        $response->assertRedirect(route('sales.deliveries.create', $sale));
        $response->assertSessionHasErrors(['quantities']);
        $this->assertDatabaseCount('delivery_notes', 0);
    }

    /** @return array{Sale, Product, Warehouse, User} */
    private function makePostedSale(): array
    {
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        app(InventoryService::class)->increase($product, $warehouse, '20', StockMovementType::OpeningBalance, $salesUser);
        $sale = Sale::create([
            'number' => 'INV-P0-001',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today(),
            'due_date' => today()->addDays(14),
            'status' => 'draft',
            'created_by' => $salesUser->id,
        ]);
        $sale->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10000, 'line_total' => 50000]);
        app(SalesService::class)->post($sale, $salesUser);

        return [$sale, $product, $warehouse, $salesUser];
    }
}
