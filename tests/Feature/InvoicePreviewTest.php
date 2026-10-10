<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InvoicePreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_user_can_preview_a_posted_sales_invoice(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $product = Product::factory()->create(['name' => 'Robong Holo 600ml']);
        $sale = Sale::create([
            'number' => 'INV-2026-000099',
            'customer_id' => Customer::factory()->create(['name' => 'Toko Maju'])->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(14),
            'status' => 'unpaid',
            'total' => 50000,
            'paid_amount' => 0,
            'outstanding_amount' => 50000,
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'line_total' => 50000,
        ]);

        $response = $this->actingAs($user)->get(route('sales.invoice', $sale));

        $response->assertOk()
            ->assertSee('FAKTUR PENJUALAN')
            ->assertSee('INV-2026-000099')
            ->assertSee('Toko Maju')
            ->assertSee('Robong Holo 600ml')
            ->assertSee('size: 9.5in 5.5in', false)
            ->assertSee('Cetak Faktur');
    }

    public function test_draft_sales_invoice_cannot_be_previewed(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $sale = Sale::create([
            'number' => 'INV-DRAFT',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'payment_type' => 'cash',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('sales.invoice', $sale))
            ->assertNotFound();
    }

    public function test_transfer_sales_invoice_displays_the_sender_bank(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $product = Product::factory()->create(['name' => 'Nanwani 330ml']);
        $sale = Sale::create([
            'number' => 'INV-TRANSFER',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'payment_type' => 'cash',
            'payment_method' => 'transfer',
            'sender_bank' => 'Bank Papua',
            'status' => 'paid',
            'total' => 50000,
            'paid_amount' => 50000,
            'outstanding_amount' => 0,
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        $sale->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'line_total' => 50000,
        ]);

        $response = $this->actingAs($user)->get(route('sales.invoice', $sale));

        $response->assertOk()
            ->assertSeeText('Transfer')
            ->assertSeeText('Bank pengirim')
            ->assertSeeText('Bank Papua');
    }

    public function test_purchasing_user_can_preview_a_posted_goods_receipt(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create(['name' => 'Preform 16 gram']);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-2026-000088',
            'supplier_id' => Supplier::factory()->create(['name' => 'Supplier Papua'])->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'payment_type' => 'credit',
            'due_date' => today()->addDays(30),
            'status' => 'completed',
            'total' => 75000,
            'created_by' => $user->id,
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => 150,
            'received_quantity' => 150,
            'unit_price' => 500,
            'line_total' => 75000,
        ]);
        $goodsReceipt = GoodsReceipt::create([
            'number' => 'GR-2026-000077',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => today(),
            'status' => 'posted',
            'total' => 75000,
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);
        $goodsReceipt->items()->create([
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'quantity' => 150,
            'unit_price' => 500,
            'line_total' => 75000,
        ]);

        $response = $this->actingAs($user)->get(route('purchasing.receipts.invoice', $goodsReceipt));

        $response->assertOk()
            ->assertSee('BUKTI PENERIMAAN BARANG')
            ->assertSee('GR-2026-000077')
            ->assertSee('PO-2026-000088')
            ->assertSee('Supplier Papua')
            ->assertSee('Preform 16 gram')
            ->assertSee('Cetak Bukti');
    }

    public function test_users_without_module_permission_cannot_preview_documents(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse]);
        $sale = Sale::create([
            'number' => 'INV-FORBIDDEN',
            'customer_id' => Customer::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'sale_date' => today(),
            'payment_type' => 'cash',
            'status' => 'paid',
            'created_by' => $user->id,
            'posted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('sales.invoice', $sale))
            ->assertForbidden();
    }
}
