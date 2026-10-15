<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\DeliveryService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\PurchasingService;
use App\Services\SalesService;
use App\Services\SupplierInvoiceService;
use App\StockMovementType;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejected_purchase_order_can_be_revised_and_resubmitted_as_draft(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $originalProduct = Product::factory()->create();
        $replacementProduct = Product::factory()->create();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $warehouse, 'draft');
        $purchaseOrder->items()->create([
            'product_id' => $originalProduct->id,
            'ordered_quantity' => 10,
            'unit_price' => 1000,
            'line_total' => 10000,
        ]);

        $this->actingAs($owner)->post(route('purchasing.reject', $purchaseOrder), [
            'reason' => 'Harga supplier perlu dikoreksi.',
        ])->assertRedirect();

        $this->assertSame('rejected', $purchaseOrder->fresh()->status);
        $this->assertSame('Harga supplier perlu dikoreksi.', $purchaseOrder->fresh()->rejection_reason);

        $this->actingAs($admin)->put(route('purchasing.update', $purchaseOrder), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today()->format('Y-m-d'),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(14)->format('Y-m-d'),
            'notes' => 'Harga sudah diperbaiki.',
            'items' => [[
                'product_id' => $replacementProduct->id,
                'quantity' => 8,
                'unit_price' => 900,
            ]],
        ])->assertRedirect(route('purchasing.index'));

        $purchaseOrder->refresh();
        $this->assertSame('draft', $purchaseOrder->status);
        $this->assertNull($purchaseOrder->rejection_reason);
        $this->assertSame('7200.00', $purchaseOrder->total);
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_order_id' => $purchaseOrder->id, 'product_id' => $originalProduct->id]);
        $this->assertDatabaseHas('purchase_order_items', ['purchase_order_id' => $purchaseOrder->id, 'product_id' => $replacementProduct->id]);
    }

    public function test_unreceived_purchase_order_can_be_cancelled_but_received_order_requires_a_return(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $warehouse, 'approved');
        $item = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => 5,
            'unit_price' => 1000,
            'line_total' => 5000,
        ]);

        $this->actingAs($admin)->post(route('purchasing.cancel', $purchaseOrder), [
            'reason' => 'Pesanan tidak jadi dikirim.',
        ])->assertRedirect();

        $this->assertSame('cancelled', $purchaseOrder->fresh()->status);
        $this->assertSame('Pesanan tidak jadi dikirim.', $purchaseOrder->fresh()->cancellation_reason);

        $receivedOrder = $this->purchaseOrder($admin, $supplier, $warehouse, 'approved', 'PO-RECEIVED');
        $receivedItem = $receivedOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => 5,
            'unit_price' => 1000,
            'line_total' => 5000,
        ]);
        app(PurchasingService::class)->receive($receivedOrder, [$receivedItem->id => '5'], $admin);

        $this->actingAs($admin)
            ->from(route('purchasing.index'))
            ->post(route('purchasing.cancel', $receivedOrder), ['reason' => 'Salah dokumen.'])
            ->assertRedirect(route('purchasing.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame('completed', $receivedOrder->fresh()->status);
        $this->assertSame('5.000', $item->fresh()->ordered_quantity);
    }

    public function test_sales_draft_can_be_edited_and_cancelled_without_changing_stock(): void
    {
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $sale = $this->sale($salesUser, $customer, $warehouse, 'draft');
        $sale->items()->create(['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 10000, 'line_total' => 20000]);

        $this->actingAs($salesUser)->put(route('sales.update', $sale), [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today()->format('Y-m-d'),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(14)->format('Y-m-d'),
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 3,
                'unit_price' => 11000,
            ]],
        ])->assertRedirect(route('sales.index'));

        $this->assertSame('33000.00', $sale->fresh()->total);

        $this->actingAs($salesUser)->post(route('sales.cancel', $sale), [
            'reason' => 'Pelanggan membatalkan pesanan.',
        ])->assertRedirect();

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertSame('Pelanggan membatalkan pesanan.', $sale->fresh()->cancellation_reason);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_credit_sales_return_restores_stock_and_reduces_receivable(): void
    {
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        app(InventoryService::class)->increase($product, $warehouse, '10', StockMovementType::OpeningBalance, $salesUser);
        $sale = $this->sale($salesUser, Customer::factory()->create(), $warehouse, 'draft');
        $sale->items()->create(['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 10000, 'line_total' => 40000]);
        app(SalesService::class)->post($sale, $salesUser);
        $saleItem = $sale->items()->firstOrFail();
        app(DeliveryService::class)->post($sale, [$saleItem->id => '4'], today()->format('Y-m-d'), null, $salesUser);

        $this->actingAs($salesUser)->post(route('sales.returns.store', $sale), [
            'submission_token' => '00000000-0000-4000-8000-000000000021',
            'return_date' => today()->format('Y-m-d'),
            'reason' => 'Dua karton rusak saat diterima pelanggan.',
            'quantities' => [$saleItem->id => 2],
        ])->assertRedirect(route('sales.index'));

        $sale->refresh();
        $this->assertSame('8.000', StockBalance::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame('20000.00', $sale->outstanding_amount);
        $this->assertSame('20000.00', $sale->returned_amount);
        $this->assertSame('partial', $sale->return_status);
        $this->assertDatabaseHas('sales_returns', ['sale_id' => $sale->id, 'total' => 20000, 'credit_amount' => 20000, 'refund_amount' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'RETURN', 'module' => 'SalesReturn']);
        $salesReturn = SalesReturn::query()->where('sale_id', $sale->id)->firstOrFail();
        $this->actingAs($salesUser)->get(route('sales.returns.print', $salesReturn))
            ->assertOk()
            ->assertSeeText('NOTA RETUR PENJUALAN');
        $report = $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get(route('reports.index'));
        $report->assertOk();
        $this->assertSame(20000.0, (float) $report->viewData('salesTotal'));
        $this->assertSame(2.0, (float) $report->viewData('productSales')->firstOrFail()->quantity_sold);
    }

    public function test_paid_sales_return_refunds_customer_from_selected_account(): void
    {
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $account = CashAccount::create(['code' => 'KAS-RETUR', 'name' => 'Kas Retur', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);
        app(InventoryService::class)->increase($product, $warehouse, '10', StockMovementType::OpeningBalance, $salesUser);
        $sale = Sale::create([
            'number' => 'INV-PAID-RETURN',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'cash_account_id' => $account->id,
            'status' => 'draft',
            'created_by' => $salesUser->id,
        ]);
        $saleItem = $sale->items()->create(['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 10000, 'line_total' => 40000]);
        app(SalesService::class)->post($sale, $salesUser);
        app(DeliveryService::class)->post($sale, [$saleItem->id => '4'], today()->format('Y-m-d'), null, $salesUser);

        $this->actingAs($salesUser)->post(route('sales.returns.store', $sale), [
            'submission_token' => '00000000-0000-4000-8000-000000000022',
            'return_date' => today()->format('Y-m-d'),
            'reason' => 'Satu karton dikembalikan.',
            'refund_account_id' => $account->id,
            'quantities' => [$saleItem->id => 1],
        ])->assertRedirect(route('sales.index'));

        $this->assertSame('30000.00', $account->fresh()->balance);
        $this->assertSame('30000.00', $sale->fresh()->paid_amount);
        $this->assertDatabaseHas('sales_returns', ['sale_id' => $sale->id, 'refund_amount' => 10000]);
        $this->assertDatabaseHas('cash_transactions', ['direction' => 'out', 'amount' => 10000]);
    }

    public function test_credit_purchase_return_reduces_stock_and_payable(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = $this->purchaseOrder($admin, Supplier::factory()->create(), $warehouse, 'approved', 'PO-CREDIT-RETURN');
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_price' => 10000, 'line_total' => 50000]);
        $receipt = app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '5'], $admin);
        $receiptItem = $receipt->items()->firstOrFail();
        app(SupplierInvoiceService::class)->create($receipt, [
            'supplier_invoice_number' => 'SUP-CREDIT-RETURN',
            'invoice_date' => today()->format('Y-m-d'),
            'total' => 50000,
        ], $admin);

        $this->actingAs($admin)->post(route('purchasing.returns.store', $receipt), [
            'submission_token' => '00000000-0000-4000-8000-000000000023',
            'return_date' => today()->format('Y-m-d'),
            'reason' => 'Dua unit tidak sesuai spesifikasi.',
            'quantities' => [$receiptItem->id => 2],
        ])->assertRedirect(route('purchasing.index'));

        $purchaseOrder->refresh();
        $this->assertSame('3.000', StockBalance::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertSame('30000.00', $purchaseOrder->outstanding_amount);
        $this->assertSame('20000.00', $purchaseOrder->returned_amount);
        $this->assertSame('partial', $purchaseOrder->return_status);
        $this->assertSame('3.000', $item->fresh()->received_quantity);
        $this->assertDatabaseHas('purchase_returns', ['purchase_order_id' => $purchaseOrder->id, 'credit_amount' => 20000, 'refund_amount' => 0]);
        $purchaseReturn = PurchaseReturn::query()->where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $this->actingAs($admin)->get(route('purchasing.returns.print', $purchaseReturn))
            ->assertOk()
            ->assertSeeText('NOTA RETUR PEMBELIAN');
        $report = $this->actingAs($admin)->get(route('reports.index'));
        $report->assertOk();
        $this->assertSame(30000.0, (float) $report->viewData('purchaseTotal'));
    }

    public function test_paid_purchase_return_records_supplier_refund(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $account = CashAccount::create(['code' => 'BANK-RETUR', 'name' => 'Bank Retur', 'type' => 'bank', 'balance' => 100000, 'is_active' => true]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-PAID-RETURN',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'payment_type' => 'cash',
            'cash_account_id' => $account->id,
            'status' => 'approved',
            'total' => 50000,
            'created_by' => $admin->id,
        ]);
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => 5, 'unit_price' => 10000, 'line_total' => 50000]);
        $receipt = app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '5'], $admin);
        $receiptItem = $receipt->items()->firstOrFail();
        $invoice = app(SupplierInvoiceService::class)->create($receipt, [
            'supplier_invoice_number' => 'SUP-PAID-RETURN',
            'invoice_date' => today()->format('Y-m-d'),
            'total' => 50000,
        ], $admin);
        app(PaymentService::class)->paySupplier($invoice, $account, '50000', $admin);

        $this->actingAs($admin)->post(route('purchasing.returns.store', $receipt), [
            'submission_token' => '00000000-0000-4000-8000-000000000024',
            'return_date' => today()->format('Y-m-d'),
            'reason' => 'Supplier menerima retur.',
            'refund_account_id' => $account->id,
            'quantities' => [$receiptItem->id => 2],
        ])->assertRedirect(route('purchasing.index'));

        $this->assertSame('70000.00', $account->fresh()->balance);
        $this->assertSame('30000.00', $purchaseOrder->fresh()->paid_amount);
        $this->assertDatabaseHas('purchase_returns', ['purchase_order_id' => $purchaseOrder->id, 'refund_amount' => 20000]);
        $this->assertDatabaseHas('cash_transactions', ['direction' => 'in', 'amount' => 20000]);
    }

    public function test_purchase_order_has_a_print_preview(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $purchaseOrder = $this->purchaseOrder($admin, Supplier::factory()->create(['name' => 'Supplier Papua']), Warehouse::factory()->create(), 'approved', 'PO-PRINT-001');
        $product = Product::factory()->create(['name' => 'Preform 330ml']);
        $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => 10, 'unit_price' => 1000, 'line_total' => 10000]);

        $this->actingAs($admin)->get(route('purchasing.print', $purchaseOrder))
            ->assertOk()
            ->assertSeeText('PURCHASE ORDER')
            ->assertSeeText('PO-PRINT-001')
            ->assertSeeText('Supplier Papua')
            ->assertSeeText('Preform 330ml')
            ->assertSeeText('Cetak PO');
    }

    #[DataProvider('purchaseOrderPreviewRoles')]
    public function test_owner_and_warehouse_can_preview_a_draft_purchase_order(UserRole $role): void
    {
        $creator = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = User::factory()->create(['role' => $role]);
        $purchaseOrder = $this->purchaseOrder(
            $creator,
            Supplier::factory()->create(['name' => 'Supplier Draft UAT']),
            Warehouse::factory()->create(),
            'draft',
            'PO-DRAFT-PREVIEW',
        );
        $product = Product::factory()->create(['name' => 'Preform Draft UAT']);
        $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => 10,
            'unit_price' => 1000,
            'line_total' => 10000,
        ]);

        $this->actingAs($viewer)
            ->get(route('purchasing.index'))
            ->assertOk()
            ->assertSeeText('Preview PO');

        $this->actingAs($viewer)
            ->get(route('purchasing.print', $purchaseOrder))
            ->assertOk()
            ->assertSeeText('PO-DRAFT-PREVIEW')
            ->assertSeeText('DRAFT — BELUM DISETUJUI')
            ->assertSeeText('Cetak PO');
    }

    /** @return array<string, array{UserRole}> */
    public static function purchaseOrderPreviewRoles(): array
    {
        return [
            'owner' => [UserRole::Owner],
            'warehouse' => [UserRole::Warehouse],
        ];
    }

    public function test_sales_user_cannot_preview_a_purchase_order(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Admin]);
        $purchaseOrder = $this->purchaseOrder(
            $creator,
            Supplier::factory()->create(),
            Warehouse::factory()->create(),
            'draft',
            'PO-FORBIDDEN-PREVIEW',
        );

        $this->actingAs(User::factory()->create(['role' => UserRole::Sales]))
            ->get(route('purchasing.print', $purchaseOrder))
            ->assertForbidden();
    }

    public function test_warehouse_cannot_post_purchase_or_sales_returns(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $purchaseOrder = $this->purchaseOrder($warehouseUser, Supplier::factory()->create(), Warehouse::factory()->create(), 'completed', 'PO-FORBIDDEN-RETURN');
        $goodsReceipt = GoodsReceipt::create([
            'number' => 'GR-FORBIDDEN-RETURN',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => today(),
            'status' => 'posted',
            'created_by' => $warehouseUser->id,
            'posted_at' => now(),
        ]);
        $sale = $this->sale($warehouseUser, Customer::factory()->create(), Warehouse::factory()->create(), 'paid');

        $this->actingAs($warehouseUser)
            ->post(route('purchasing.returns.store', $goodsReceipt), [
                'return_date' => today()->format('Y-m-d'),
                'reason' => 'Tidak berhak.',
                'quantities' => [],
            ])
            ->assertForbidden();
        $this->actingAs($warehouseUser)
            ->post(route('sales.returns.store', $sale), [
                'return_date' => today()->format('Y-m-d'),
                'reason' => 'Tidak berhak.',
                'quantities' => [],
            ])
            ->assertForbidden();

        $this->assertSame('completed', $purchaseOrder->fresh()->status);
        $this->assertDatabaseCount('purchase_returns', 0);
        $this->assertDatabaseCount('sales_returns', 0);
    }

    private function purchaseOrder(
        User $user,
        Supplier $supplier,
        Warehouse $warehouse,
        string $status,
        string $number = 'PO-LIFECYCLE',
    ): PurchaseOrder {
        return PurchaseOrder::create([
            'number' => $number,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(14),
            'status' => $status,
            'total' => 10000,
            'created_by' => $user->id,
        ]);
    }

    private function sale(User $user, Customer $customer, Warehouse $warehouse, string $status): Sale
    {
        return Sale::create([
            'number' => 'INV-LIFECYCLE-'.str()->random(8),
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today(),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(14),
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }
}
