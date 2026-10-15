<?php

namespace Tests\Feature;

use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryTransferService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTransferTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_posting_transfer_moves_stock_between_warehouses_once(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse]);
        $source = Warehouse::factory()->create();
        $destination = Warehouse::factory()->create();
        $product = Product::factory()->create();
        StockBalance::query()->create(['warehouse_id' => $source->id, 'product_id' => $product->id, 'quantity' => 20]);
        $transfer = InventoryTransfer::query()->create(['number' => 'TRF-TEST', 'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id, 'transfer_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
        $transfer->items()->create(['product_id' => $product->id, 'quantity' => 7]);

        app(InventoryTransferService::class)->post($transfer, $user, '2a2fd9a6-d2f9-47e2-96f3-f4a2b17036b7');
        $this->assertSame('13.000', StockBalance::query()->whereBelongsTo($source)->whereBelongsTo($product)->value('quantity'));
        $this->assertSame('7.000', StockBalance::query()->whereBelongsTo($destination)->whereBelongsTo($product)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 2);

        try {
            app(InventoryTransferService::class)->post($transfer, $user, '2a2fd9a6-d2f9-47e2-96f3-f4a2b17036b7');
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame('Transaksi ini sudah diproses. Muat ulang halaman sebelum mencoba kembali.', $exception->errors()['submission_token'][0]);
        }

        $this->assertSame('13.000', StockBalance::query()->whereBelongsTo($source)->whereBelongsTo($product)->value('quantity'));
        $this->assertSame('7.000', StockBalance::query()->whereBelongsTo($destination)->whereBelongsTo($product)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_sales_role_cannot_access_transfer(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $this->actingAs($sales)->get(route('inventory.transfers.index'))->assertForbidden();
    }
}
