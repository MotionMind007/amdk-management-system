<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\DailyProduction;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductionType;
use App\ProductType;
use App\Services\OperationalIntegrityService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalWorkflowUatTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cross_role_operational_cycle_keeps_documents_stock_cash_and_audit_trail_consistent(): void
    {
        $this->fakeLocalStorage();
        $this->travelTo('2026-10-12 09:00:00');

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = Warehouse::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $cashAccount = CashAccount::query()->create([
            'code' => 'KAS-UAT',
            'name' => 'Kas UAT',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);
        $preform = Product::factory()->create([
            'name' => 'Preform UAT',
            'type' => ProductType::RawMaterial,
        ]);
        $bottle = Product::factory()->create([
            'name' => 'Botol UAT 600ml',
            'type' => ProductType::PackagingMaterial,
        ]);
        $finishedGood = Product::factory()->create([
            'name' => 'Robong Holo UAT 600ml',
            'type' => ProductType::FinishedGood,
        ]);
        ProductComposition::query()->create([
            'product_id' => $finishedGood->id,
            'component_product_id' => $bottle->id,
            'quantity' => 1,
        ]);

        $this->actingAs($admin)->post(route('finance.transactions.store'), [
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => '2026-10-12',
            'direction' => 'in',
            'amount' => 100000,
            'description' => 'Saldo kas awal UAT',
        ])->assertRedirect(route('finance.index'));

        $this->assertSame('100000.00', $cashAccount->fresh()->balance);

        $this->actingAs($admin)->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-10-12',
            'payment_type' => 'credit',
            'due_date' => '2026-10-26',
            'items' => [[
                'product_id' => $preform->id,
                'quantity' => 100,
                'unit_price' => 100,
            ]],
        ])->assertRedirect(route('purchasing.index'));

        $purchaseOrder = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('draft', $purchaseOrder->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => 'purchase-order-awaiting-approval',
        ]);

        $this->actingAs($owner)
            ->from(route('purchasing.index'))
            ->post(route('purchasing.approve', $purchaseOrder))
            ->assertRedirect(route('purchasing.index'));

        $this->assertSame('approved', $purchaseOrder->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $warehouseUser->id,
            'type' => 'goods-receipt-expected',
        ]);

        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();
        $this->actingAs($warehouseUser)->post(route('purchasing.receive.store', $purchaseOrder), [
            'receipt_date' => '2026-10-12',
            'quantities' => [$purchaseOrderItem->id => 100],
            'proof' => UploadedFile::fake()->image('bukti-penerimaan.jpg'),
        ])->assertRedirect(route('purchasing.index'));

        $goodsReceipt = $purchaseOrder->goodsReceipts()->firstOrFail();
        Storage::disk('local')->assertExists($goodsReceipt->proof_path);
        $this->assertSame('completed', $purchaseOrder->fresh()->status);
        $this->assertSame('100.000', $this->stockQuantity($preform, $warehouse));

        $this->actingAs($admin)->post(route('purchasing.supplier-invoices.store', $goodsReceipt), [
            'supplier_invoice_number' => 'SUP-UAT-001',
            'invoice_date' => '2026-10-12',
            'due_date' => '2026-10-26',
            'total' => 10000,
            'proof' => UploadedFile::fake()->image('faktur-supplier.jpg'),
        ])->assertRedirect(route('finance.index'));

        $supplierInvoice = $goodsReceipt->supplierInvoice()->firstOrFail();
        $this->actingAs($admin)->post(route('finance.supplier-payment.store', $supplierInvoice), [
            'cash_account_id' => $cashAccount->id,
            'payment_date' => '2026-10-12',
            'amount' => 10000,
            'notes' => 'Pelunasan bahan baku UAT',
        ])->assertRedirect(route('purchasing.index'));

        $this->assertSame('paid', $supplierInvoice->fresh()->status);
        $this->assertSame('90000.00', $cashAccount->fresh()->balance);

        $this->actingAs($warehouseUser)->post(route('production.store'), [
            'production_type' => ProductionType::Packaging->value,
            'production_date' => '2026-10-12',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $bottle->id,
                'quantity' => 60,
                'rejected_quantity' => 0,
            ]],
            'materials' => [[
                'product_id' => $preform->id,
                'quantity' => 60,
            ]],
        ])->assertRedirect(route('production.index'));

        $packagingProduction = DailyProduction::query()->firstOrFail();
        $this->actingAs($warehouseUser)
            ->from(route('production.index'))
            ->post(route('production.post', $packagingProduction))
            ->assertRedirect(route('production.index'));

        $this->assertSame('40.000', $this->stockQuantity($preform, $warehouse));
        $this->assertSame('60.000', $this->stockQuantity($bottle, $warehouse));

        $this->actingAs($warehouseUser)->post(route('production.store'), [
            'production_type' => ProductionType::FinishedGood->value,
            'production_date' => '2026-10-12',
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $finishedGood->id,
                'quantity' => 50,
                'rejected_quantity' => 0,
            ]],
        ])->assertRedirect(route('production.index'));

        $finishedProduction = DailyProduction::query()->latest('id')->firstOrFail();
        $this->actingAs($warehouseUser)
            ->from(route('production.index'))
            ->post(route('production.post', $finishedProduction))
            ->assertRedirect(route('production.index'));

        $this->assertSame('10.000', $this->stockQuantity($bottle, $warehouse));
        $this->assertSame('50.000', $this->stockQuantity($finishedGood, $warehouse));

        $this->actingAs($salesUser)->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-12',
            'payment_type' => 'credit',
            'due_date' => '2026-10-26',
            'items' => [[
                'product_id' => $finishedGood->id,
                'quantity' => 20,
                'unit_price' => 1000,
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->firstOrFail();
        $this->actingAs($salesUser)
            ->from(route('sales.index'))
            ->post(route('sales.post', $sale))
            ->assertRedirect(route('sales.index'));

        $this->assertSame('unpaid', $sale->fresh()->status);
        $this->assertSame('50.000', $this->stockQuantity($finishedGood, $warehouse));

        $saleItem = $sale->items()->firstOrFail();
        $this->actingAs($warehouseUser)->post(route('sales.deliveries.store', $sale), [
            'delivery_date' => '2026-10-12',
            'quantities' => [$saleItem->id => 20],
        ])->assertRedirect(route('sales.deliveries.index'));

        $this->assertSame('complete', $sale->fresh()->delivery_status);
        $this->assertSame('30.000', $this->stockQuantity($finishedGood, $warehouse));

        $this->actingAs($salesUser)->post(route('finance.customer-payment.store', $sale), [
            'payment_method' => 'cash',
            'cash_account_id' => $cashAccount->id,
            'payment_date' => '2026-10-12',
            'amount' => 20000,
            'notes' => 'Pelunasan penjualan UAT',
        ])->assertRedirect(route('sales.index'));

        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertSame('110000.00', $cashAccount->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 3);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'CREATE',
            'module' => 'PurchaseOrder',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'APPROVE',
            'module' => 'PurchaseOrder',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $warehouseUser->id,
            'action' => 'POST',
            'module' => 'Production',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $salesUser->id,
            'action' => 'PAYMENT',
            'module' => 'Receivable',
        ]);
        $integrity = app(OperationalIntegrityService::class)->check();

        $this->assertTrue($integrity['healthy'], json_encode($integrity, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function stockQuantity(Product $product, Warehouse $warehouse): string
    {
        return (string) $product->stockBalances()
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');
    }
}
