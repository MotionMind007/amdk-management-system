<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\InventoryService;
use App\Services\PaymentService;
use App\Services\SalesService;
use App\StockMovementType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_posted_credit_sale_reduces_stock_and_payment_reduces_receivable(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        app(InventoryService::class)->increase($product, $warehouse, '20', StockMovementType::OpeningBalance, $user);
        $sale = Sale::create(['number' => 'INV-2026-000001', 'customer_id' => Customer::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'sale_date' => today(), 'due_date' => today()->addDays(14), 'status' => 'draft', 'created_by' => $user->id]);
        $sale->items()->create(['product_id' => $product->id, 'quantity' => '5', 'unit_price' => '10000', 'line_total' => 0]);

        app(SalesService::class)->post($sale, $user);

        $this->assertSame('15.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('50000.00', $sale->fresh()->outstanding_amount);
        $this->assertSame('unpaid', $sale->fresh()->status);

        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);
        app(PaymentService::class)->receiveCustomerPayment($sale, $account, '20000', $user);

        $this->assertSame('30000.00', $sale->fresh()->outstanding_amount);
        $this->assertSame('partially_paid', $sale->fresh()->status);
        $this->assertSame('20000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_posted_transfer_sale_increases_bank_account_without_creating_receivable(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $cashAccount = CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);
        $bankAccount = CashAccount::create(['code' => 'BANK-TEST', 'name' => 'Bank', 'type' => 'bank', 'balance' => 0, 'is_active' => true]);
        app(InventoryService::class)->increase($product, $warehouse, '20', StockMovementType::OpeningBalance, $user);
        $sale = Sale::create(['number' => 'INV-2026-000002', 'customer_id' => Customer::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'sale_date' => today(), 'payment_type' => 'cash', 'payment_method' => 'transfer', 'sender_bank' => 'Bank Papua', 'cash_account_id' => $bankAccount->id, 'status' => 'draft', 'created_by' => $user->id]);
        $sale->items()->create(['product_id' => $product->id, 'quantity' => '5', 'unit_price' => '10000', 'line_total' => 0]);

        app(SalesService::class)->post($sale, $user);

        $this->assertSame('15.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('50000.00', $sale->fresh()->paid_amount);
        $this->assertSame('0.00', $sale->fresh()->outstanding_amount);
        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertSame('0.00', $cashAccount->fresh()->balance);
        $this->assertSame('50000.00', $bankAccount->fresh()->balance);
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_posting_rejects_master_data_deactivated_after_the_draft_was_created(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $customer = Customer::factory()->create();
        app(InventoryService::class)->increase($product, $warehouse, '20', StockMovementType::OpeningBalance, $user);
        $sale = Sale::create(['number' => 'INV-2026-000003', 'customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'sale_date' => today(), 'status' => 'draft', 'created_by' => $user->id]);
        $sale->items()->create(['product_id' => $product->id, 'quantity' => '5', 'unit_price' => '10000', 'line_total' => 0]);
        $customer->update(['status' => 'inactive']);

        try {
            app(SalesService::class)->post($sale, $user);
            $this->fail('Posting seharusnya ditolak ketika pelanggan sudah tidak aktif.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Pelanggan tidak aktif atau tidak tersedia.'], $exception->errors()['customer_id']);
        }

        $this->assertSame('draft', $sale->fresh()->status);
        $this->assertSame('20.000', StockBalance::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_payment_rejects_account_deactivated_after_it_was_selected(): void
    {
        $user = User::factory()->create();
        $account = CashAccount::create(['code' => 'KAS-LAMA', 'name' => 'Kas Lama', 'type' => 'cash', 'balance' => 0, 'is_active' => false]);
        $sale = Sale::create([
            'number' => 'INV-2026-000004',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'status' => 'unpaid',
            'total' => 50000,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
        ]);

        try {
            app(PaymentService::class)->receiveCustomerPayment($sale, $account, '20000', $user);
            $this->fail('Pembayaran seharusnya ditolak untuk akun yang tidak aktif.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Akun kas atau bank tidak aktif atau tidak tersedia.'], $exception->errors()['cash_account_id']);
        }

        $this->assertSame('50000.00', $sale->fresh()->outstanding_amount);
        $this->assertSame('0.00', $account->fresh()->balance);
        $this->assertDatabaseCount('customer_payments', 0);
    }
}
