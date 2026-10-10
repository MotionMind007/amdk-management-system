<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_form_shows_payment_methods_and_cash_balances(): void
    {
        $user = User::factory()->administrator()->create();
        CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 3000000, 'is_active' => true]);
        CashAccount::create(['code' => 'BANK-TEST', 'name' => 'Bank', 'type' => 'bank', 'balance' => 1250000, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('sales.create'));

        $response->assertOk()->assertSee([
            'Metode Pembayaran',
            'Transfer',
            'Nama Bank Pengirim',
            'Kas Kantor',
            'Rp 3.000.000',
            'Rp 1.250.000',
            'Saldo Kas Kantor',
            'Saldo Bank',
        ])->assertDontSee('Masuk ke Kas/Bank');
    }

    public function test_credit_sale_requires_a_due_date(): void
    {
        $user = User::factory()->administrator()->create();
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);

        $response = $this->actingAs($user)->from(route('sales.create'))->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-08',
            'payment_type' => 'credit',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25000],
            ],
        ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors([
            'due_date' => 'Tanggal jatuh tempo wajib diisi untuk penjualan kredit.',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_cash_sale_automatically_uses_the_active_cash_account(): void
    {
        $user = User::factory()->administrator()->create();
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $cashAccount = CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-08',
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25000],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', [
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'sender_bank' => null,
            'due_date' => null,
            'cash_account_id' => $cashAccount->id,
        ]);
    }

    public function test_transfer_sale_stores_sender_bank_and_automatically_uses_the_active_bank_account(): void
    {
        $user = User::factory()->administrator()->create();
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);
        $bankAccount = CashAccount::query()->where('code', 'BANK')->firstOrFail();

        $response = $this->actingAs($user)->post(route('sales.store'), [
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-08',
            'payment_type' => 'cash',
            'payment_method' => 'transfer',
            'sender_bank' => 'Bank Papua',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25000],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));
        $this->assertDatabaseHas('sales', [
            'payment_type' => 'cash',
            'payment_method' => 'transfer',
            'sender_bank' => 'Bank Papua',
            'cash_account_id' => $bankAccount->id,
        ]);
    }

    public function test_transfer_sale_requires_the_sender_bank_name(): void
    {
        $user = User::factory()->administrator()->create();
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);

        $response = $this->actingAs($user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'sale_date' => '2026-10-08',
                'payment_type' => 'cash',
                'payment_method' => 'transfer',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25000],
                ],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors([
            'sender_bank' => 'Nama bank pengirim wajib diisi untuk pembayaran transfer.',
        ]);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_credit_purchase_requires_a_due_date(): void
    {
        $user = User::factory()->administrator()->create();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->from(route('purchasing.create'))->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-10-08',
            'payment_type' => 'credit',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 500],
            ],
        ]);

        $response->assertRedirect(route('purchasing.create'));
        $response->assertSessionHasErrors([
            'due_date' => 'Tanggal jatuh tempo wajib diisi untuk pembelian kredit.',
        ]);
        $this->assertDatabaseCount('purchase_orders', 0);
    }

    public function test_cash_purchase_stores_the_cash_account_without_a_due_date(): void
    {
        $user = User::factory()->administrator()->create();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $cashAccount = CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 100000, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-10-08',
            'payment_type' => 'cash',
            'cash_account_id' => $cashAccount->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 500],
            ],
        ]);

        $response->assertRedirect(route('purchasing.index'));
        $this->assertDatabaseHas('purchase_orders', [
            'payment_type' => 'cash',
            'due_date' => null,
            'cash_account_id' => $cashAccount->id,
        ]);
    }

    public function test_sale_rejects_inactive_customer_warehouse_and_product(): void
    {
        $user = User::factory()->administrator()->create();
        $customer = Customer::factory()->create(['status' => 'inactive']);
        $warehouse = Warehouse::factory()->create(['is_active' => false]);
        $product = Product::factory()->create([
            'type' => ProductType::FinishedGood,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('sales.create'))
            ->post(route('sales.store'), [
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'sale_date' => '2026-10-08',
                'payment_type' => 'credit',
                'due_date' => '2026-10-22',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 25000],
                ],
            ]);

        $response->assertRedirect(route('sales.create'));
        $response->assertSessionHasErrors(['customer_id', 'warehouse_id', 'items.0.product_id']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_purchase_rejects_inactive_supplier_warehouse_and_product(): void
    {
        $user = User::factory()->administrator()->create();
        $supplier = Supplier::factory()->create(['status' => 'inactive']);
        $warehouse = Warehouse::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)
            ->from(route('purchasing.create'))
            ->post(route('purchasing.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => '2026-10-08',
                'payment_type' => 'credit',
                'due_date' => '2026-10-22',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 500],
                ],
            ]);

        $response->assertRedirect(route('purchasing.create'));
        $response->assertSessionHasErrors(['supplier_id', 'warehouse_id', 'items.0.product_id']);
        $this->assertDatabaseCount('purchase_orders', 0);
    }
}
