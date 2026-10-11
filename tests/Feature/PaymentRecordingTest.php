<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentRecordingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_customer_bank_payment_requires_transfer_proof(): void
    {
        $user = User::factory()->administrator()->create();
        $sale = $this->makeReceivable($user);
        $bank = CashAccount::create(['code' => 'BANK-P0', 'name' => 'Bank P0', 'type' => 'bank', 'balance' => 0, 'is_active' => true]);

        $response = $this->actingAs($user)
            ->from(route('finance.customer-payment.create', $sale))
            ->post(route('finance.customer-payment.store', $sale), [
                'cash_account_id' => $bank->id,
                'payment_date' => '2026-10-11',
                'amount' => 20000,
            ]);

        $response->assertRedirect(route('finance.customer-payment.create', $sale));
        $response->assertSessionHasErrors(['proof' => 'Bukti transfer wajib diunggah untuk pembayaran ke rekening bank.']);
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_customer_partial_payment_stores_date_notes_and_proof(): void
    {
        Storage::fake('local');
        $user = User::factory()->administrator()->create();
        $sale = $this->makeReceivable($user);
        $bank = CashAccount::create(['code' => 'BANK-P1', 'name' => 'Bank P1', 'type' => 'bank', 'balance' => 0, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('finance.customer-payment.store', $sale), [
            'cash_account_id' => $bank->id,
            'payment_date' => '2026-10-10',
            'amount' => 20000,
            'notes' => 'Transfer termin pertama',
            'proof' => UploadedFile::fake()->image('transfer.jpg'),
        ]);

        $payment = $sale->payments()->firstOrFail();
        $response->assertRedirect(route('sales.index'));
        $this->assertSame('30000.00', $sale->fresh()->outstanding_amount);
        $this->assertSame('partially_paid', $sale->fresh()->status);
        $this->assertSame('2026-10-10', $payment->payment_date->format('Y-m-d'));
        $this->assertSame('Transfer termin pertama', $payment->notes);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_supplier_partial_payment_updates_invoice_po_and_bank_balance(): void
    {
        Storage::fake('local');
        $user = User::factory()->administrator()->create();
        $invoice = $this->makeSupplierInvoice($user);
        $bank = CashAccount::create(['code' => 'BANK-P2', 'name' => 'Bank P2', 'type' => 'bank', 'balance' => 100000, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('finance.supplier-payment.store', $invoice), [
            'cash_account_id' => $bank->id,
            'payment_date' => '2026-10-09',
            'amount' => 30000,
            'notes' => 'Termin pertama',
            'proof' => UploadedFile::fake()->image('transfer-supplier.jpg'),
        ]);

        $payment = $invoice->payments()->firstOrFail();
        $response->assertRedirect(route('purchasing.index'));
        $this->assertSame('20000.00', $invoice->fresh()->outstanding_amount);
        $this->assertSame('partially_paid', $invoice->fresh()->status);
        $this->assertSame('20000.00', $invoice->purchaseOrder->fresh()->outstanding_amount);
        $this->assertSame('70000.00', $bank->fresh()->balance);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    private function makeReceivable(User $user): Sale
    {
        return Sale::create([
            'number' => 'INV-PAY-P0',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'status' => 'unpaid',
            'delivery_status' => 'pending',
            'total' => 50000,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
        ]);
    }

    private function makeSupplierInvoice(User $user): SupplierInvoice
    {
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-PAY-P0',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'completed',
            'total' => 50000,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
        ]);
        $receipt = GoodsReceipt::create([
            'number' => 'GR-PAY-P0',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => today(),
            'status' => 'posted',
            'total' => 50000,
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);

        return SupplierInvoice::create([
            'number' => 'BILL-PAY-P0',
            'goods_receipt_id' => $receipt->id,
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_invoice_number' => 'SUP-PAY-P0',
            'invoice_date' => today(),
            'status' => 'unpaid',
            'total' => 50000,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
        ]);
    }
}
