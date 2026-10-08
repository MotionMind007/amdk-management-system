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
}
