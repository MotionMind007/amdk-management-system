<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierInvoiceFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_records_supplier_invoice_from_posted_goods_receipt(): void
    {
        $this->fakeLocalStorage();
        [$user, $purchaseOrder, $goodsReceipt] = $this->makeGoodsReceipt();

        $response = $this->actingAs($user)->post(route('purchasing.supplier-invoices.store', $goodsReceipt), [
            'submission_token' => '00000000-0000-4000-8000-000000000041',
            'supplier_invoice_number' => 'SUP-INV-001',
            'invoice_date' => '2026-10-11',
            'due_date' => '2026-10-25',
            'total' => 50000,
            'notes' => 'Tagihan bahan baku Oktober',
            'proof' => UploadedFile::fake()->image('faktur.jpg'),
        ]);

        $invoice = $goodsReceipt->supplierInvoice()->firstOrFail();
        $response->assertRedirect(route('finance.index'));
        $this->assertSame('50000.00', $invoice->outstanding_amount);
        $this->assertSame('50000.00', $purchaseOrder->fresh()->outstanding_amount);
        Storage::disk('local')->assertExists($invoice->proof_path);
        $this->assertDatabaseHas('audit_logs', ['module' => 'SupplierInvoice', 'entity_id' => $invoice->id]);
    }

    public function test_goods_receipt_can_only_have_one_supplier_invoice(): void
    {
        [$user, $purchaseOrder, $goodsReceipt] = $this->makeGoodsReceipt();
        $goodsReceipt->supplierInvoice()->create([
            'number' => 'BILL-2026-000001',
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_invoice_number' => 'SUP-INV-001',
            'invoice_date' => today(),
            'status' => 'unpaid',
            'total' => 50000,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('purchasing.supplier-invoices.create', $goodsReceipt))
            ->assertUnprocessable();

        $this->assertDatabaseCount('supplier_invoices', 1);
    }

    public function test_cash_purchase_invoice_is_paid_automatically_from_selected_account(): void
    {
        $account = CashAccount::factory()->create(['balance' => 100000]);
        [$user, $purchaseOrder, $goodsReceipt] = $this->makeGoodsReceipt();
        $purchaseOrder->update(['payment_type' => 'cash', 'cash_account_id' => $account->id]);

        $this->actingAs($user)->post(route('purchasing.supplier-invoices.store', $goodsReceipt), [
            'submission_token' => '00000000-0000-4000-8000-000000000042',
            'supplier_invoice_number' => 'SUP-CASH-001',
            'invoice_date' => '2026-10-11',
            'total' => 50000,
        ])->assertRedirect(route('finance.index'));

        $invoice = $goodsReceipt->supplierInvoice()->firstOrFail();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->outstanding_amount);
        $this->assertSame('50000.00', $purchaseOrder->fresh()->paid_amount);
        $this->assertSame('0.00', $purchaseOrder->fresh()->outstanding_amount);
        $this->assertSame('50000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('supplier_payments', [
            'supplier_invoice_id' => $invoice->id,
            'cash_account_id' => $account->id,
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'direction' => 'out',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PAYMENT',
            'module' => 'Payable',
        ]);
    }

    public function test_cash_purchase_invoice_is_rolled_back_when_account_balance_is_insufficient(): void
    {
        $this->fakeLocalStorage();
        $account = CashAccount::factory()->create(['balance' => 10000]);
        [$user, $purchaseOrder, $goodsReceipt] = $this->makeGoodsReceipt();
        $purchaseOrder->update(['payment_type' => 'cash', 'cash_account_id' => $account->id]);

        $response = $this->actingAs($user)
            ->from(route('purchasing.supplier-invoices.create', $goodsReceipt))
            ->post(route('purchasing.supplier-invoices.store', $goodsReceipt), [
                'submission_token' => '00000000-0000-4000-8000-000000000043',
                'supplier_invoice_number' => 'SUP-CASH-002',
                'invoice_date' => '2026-10-11',
                'total' => 50000,
                'proof' => UploadedFile::fake()->image('faktur-gagal.jpg'),
            ]);

        $response->assertRedirect(route('purchasing.supplier-invoices.create', $goodsReceipt));
        $response->assertSessionHasErrors(['amount' => 'Saldo kas atau bank tidak mencukupi.']);
        $this->assertDatabaseCount('supplier_invoices', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('submission_tokens', 0);
        $this->assertSame('10000.00', $account->fresh()->balance);
        $this->assertSame([], Storage::disk('local')->allFiles('supplier-invoices'));
    }

    /** @return array{User, PurchaseOrder, GoodsReceipt} */
    private function makeGoodsReceipt(): array
    {
        $user = User::factory()->administrator()->create();
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-P0-001',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'completed',
            'total' => 50000,
            'created_by' => $user->id,
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => Product::factory()->create()->id,
            'ordered_quantity' => 100,
            'received_quantity' => 100,
            'unit_price' => 500,
            'line_total' => 50000,
        ]);
        $goodsReceipt = GoodsReceipt::create([
            'number' => 'GR-P0-001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => today(),
            'status' => 'posted',
            'total' => 50000,
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        $goodsReceipt->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $purchaseOrderItem->product_id,
            'quantity' => 100,
            'unit_price' => 500,
            'line_total' => 50000,
        ]);

        return [$user, $purchaseOrder, $goodsReceipt];
    }
}
