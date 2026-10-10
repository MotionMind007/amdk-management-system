<?php

namespace Tests\Feature;

use App\ExpenseCategory;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\DailyProduction;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_report_includes_posted_transactions_on_the_end_date(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $transactionDate = '2026-10-08';

        Sale::create([
            'number' => 'INV-2026-000001',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => $transactionDate,
            'status' => 'paid',
            'total' => 3000000,
            'paid_amount' => 3000000,
            'outstanding_amount' => 0,
            'created_by' => $user->id,
            'posted_at' => $transactionDate,
        ]);

        $production = DailyProduction::create([
            'number' => 'PROD-2026-000001',
            'production_date' => $transactionDate,
            'warehouse_id' => $warehouse->id,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => $transactionDate,
        ]);
        $production->items()->create([
            'product_id' => $product->id,
            'quantity' => 595,
            'rejected_quantity' => 0,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-2026-000001',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => $transactionDate,
            'status' => 'received',
            'total' => 750000,
            'outstanding_amount' => 750000,
            'created_by' => $user->id,
        ]);
        GoodsReceipt::create([
            'number' => 'GR-2026-000001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => $transactionDate,
            'status' => 'posted',
            'total' => 750000,
            'created_by' => $user->id,
            'posted_at' => $transactionDate,
        ]);

        $cashAccount = CashAccount::create([
            'code' => 'KAS',
            'name' => 'Kas Kantor',
            'type' => 'cash',
            'balance' => 2500000,
            'is_active' => true,
        ]);
        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => $transactionDate,
            'direction' => 'in',
            'amount' => 3000000,
            'description' => 'Pembayaran penjualan',
            'user_id' => $user->id,
        ]);
        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => $transactionDate,
            'direction' => 'out',
            'amount' => 500000,
            'description' => 'Pengeluaran operasional',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'start_date' => '2026-10-01',
            'end_date' => $transactionDate,
        ]));

        $response->assertOk();
        $this->assertSame(3000000.0, (float) $response->viewData('salesTotal'));
        $this->assertSame(750000.0, (float) $response->viewData('purchaseTotal'));
        $this->assertSame(595.0, (float) $response->viewData('productionTotal'));
        $this->assertSame(3000000.0, (float) $response->viewData('cashIn'));
        $this->assertSame(500000.0, (float) $response->viewData('cashOut'));
    }

    public function test_report_groups_sold_quantity_and_value_by_product(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $customer = Customer::factory()->create();
        $firstProduct = Product::factory()->create([
            'name' => 'Robong Holo 330ml',
            'type' => ProductType::FinishedGood,
        ]);
        $secondProduct = Product::factory()->create([
            'name' => 'Nanwani 600ml',
            'type' => ProductType::FinishedGood,
        ]);

        $firstSale = Sale::create([
            'number' => 'INV-REPORT-001',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-03',
            'status' => 'paid',
            'total' => 220000,
            'created_by' => $user->id,
            'posted_at' => '2026-10-03',
        ]);
        $firstSale->items()->createMany([
            ['product_id' => $firstProduct->id, 'quantity' => 10, 'unit_price' => 12000, 'line_total' => 120000],
            ['product_id' => $secondProduct->id, 'quantity' => 5, 'unit_price' => 20000, 'line_total' => 100000],
        ]);

        $secondSale = Sale::create([
            'number' => 'INV-REPORT-002',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-08',
            'status' => 'unpaid',
            'total' => 36000,
            'created_by' => $user->id,
            'posted_at' => '2026-10-08',
        ]);
        $secondSale->items()->create([
            'product_id' => $firstProduct->id,
            'quantity' => 3,
            'unit_price' => 12000,
            'line_total' => 36000,
        ]);

        $draftSale = Sale::create([
            'number' => 'INV-REPORT-DRAFT',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-08',
            'status' => 'draft',
            'total' => 1200000,
            'created_by' => $user->id,
        ]);
        $draftSale->items()->create([
            'product_id' => $firstProduct->id,
            'quantity' => 100,
            'unit_price' => 12000,
            'line_total' => 1200000,
        ]);

        $response = $this->actingAs($user)->get(route('reports.index', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-08',
        ]));

        $response->assertOk()
            ->assertSee('Penjualan per Produk')
            ->assertSee('Robong Holo 330ml')
            ->assertSee('Nanwani 600ml');

        $productSales = $response->viewData('productSales')->keyBy('name');

        $this->assertCount(2, $productSales);
        $this->assertSame(13.0, (float) $productSales->get('Robong Holo 330ml')->quantity_sold);
        $this->assertSame(156000.0, (float) $productSales->get('Robong Holo 330ml')->sales_amount);
        $this->assertSame(5.0, (float) $productSales->get('Nanwani 600ml')->quantity_sold);
        $this->assertSame(100000.0, (float) $productSales->get('Nanwani 600ml')->sales_amount);
    }

    public function test_profit_loss_report_calculates_revenue_and_manual_operating_expenses_without_double_counting_payments(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $cashAccount = CashAccount::create([
            'code' => 'KAS-PL',
            'name' => 'Kas Laba Rugi',
            'type' => 'cash',
            'balance' => 5000000,
            'is_active' => true,
        ]);

        Sale::create([
            'number' => 'INV-PL-001',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => '2026-10-05',
            'status' => 'paid',
            'total' => 3000000,
            'paid_amount' => 3000000,
            'outstanding_amount' => 0,
            'created_by' => $user->id,
            'posted_at' => '2026-10-05',
        ]);

        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => '2026-10-05',
            'direction' => 'in',
            'amount' => 3000000,
            'source_type' => 'customer_payment',
            'source_id' => 1,
            'description' => 'Pembayaran penjualan',
            'user_id' => $user->id,
        ]);
        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => '2026-10-06',
            'direction' => 'in',
            'amount' => 200000,
            'description' => 'Pendapatan lain',
            'user_id' => $user->id,
        ]);
        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => '2026-10-07',
            'direction' => 'out',
            'expense_category' => ExpenseCategory::Electricity,
            'amount' => 500000,
            'description' => 'Pembayaran listrik',
            'user_id' => $user->id,
        ]);
        CashTransaction::create([
            'cash_account_id' => $cashAccount->id,
            'transaction_date' => '2026-10-07',
            'direction' => 'out',
            'amount' => 750000,
            'source_type' => 'supplier_payment',
            'source_id' => 1,
            'description' => 'Pembayaran supplier',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.profit-loss', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

        $response->assertOk()
            ->assertSee('Laba Rugi Sederhana')
            ->assertSee('Listrik');
        $this->assertSame(3000000.0, (float) $response->viewData('salesRevenue'));
        $this->assertSame(200000.0, (float) $response->viewData('otherIncome'));
        $this->assertSame(500000.0, (float) $response->viewData('operatingExpenses'));
        $this->assertSame(2700000.0, (float) $response->viewData('profitBeforeHpp'));
    }
}
