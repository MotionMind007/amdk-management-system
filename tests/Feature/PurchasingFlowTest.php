<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchasingService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchasingFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_po_does_not_change_stock_and_partial_receipt_adds_only_received_quantity(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::create(['number' => 'PO-2026-000001', 'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'order_date' => today(), 'status' => 'approved', 'total' => '50000', 'created_by' => $user->id]);
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => '100', 'received_quantity' => 0, 'unit_price' => '500', 'line_total' => '50000']);

        $this->assertDatabaseCount('stock_balances', 0);
        $receipt = app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '80'], $user);

        $this->assertSame('80.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('40000.00', $purchaseOrder->fresh()->outstanding_amount);
        $this->assertSame('partially_received', $purchaseOrder->fresh()->status);
        $this->assertSame('40000.00', $receipt->total);
    }

    public function test_cash_purchase_receipt_reduces_cash_without_creating_payable(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 100000, 'is_active' => true]);
        $purchaseOrder = PurchaseOrder::create(['number' => 'PO-2026-000002', 'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $warehouse->id, 'order_date' => today(), 'payment_type' => 'cash', 'cash_account_id' => $account->id, 'status' => 'approved', 'total' => '50000', 'created_by' => $user->id]);
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => '100', 'received_quantity' => 0, 'unit_price' => '500', 'line_total' => '50000']);

        app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '80'], $user);

        $this->assertSame('80.000', StockBalance::query()->firstOrFail()->quantity);
        $this->assertSame('40000.00', $purchaseOrder->fresh()->paid_amount);
        $this->assertSame('0.00', $purchaseOrder->fresh()->outstanding_amount);
        $this->assertSame('60000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_receipt_rejects_supplier_deactivated_after_purchase_order_approval(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::create(['number' => 'PO-2026-000003', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'order_date' => today(), 'status' => 'approved', 'total' => '50000', 'created_by' => $user->id]);
        $item = $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => '100', 'received_quantity' => 0, 'unit_price' => '500', 'line_total' => '50000']);
        $supplier->update(['status' => 'inactive']);

        try {
            app(PurchasingService::class)->receive($purchaseOrder, [$item->id => '80'], $user);
            $this->fail('Penerimaan seharusnya ditolak ketika supplier sudah tidak aktif.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Supplier tidak aktif atau tidak tersedia.'], $exception->errors()['supplier_id']);
        }

        $this->assertSame('approved', $purchaseOrder->fresh()->status);
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_approval_rejects_master_data_deactivated_after_the_draft_was_created(): void
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::create(['number' => 'PO-2026-000004', 'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'order_date' => today(), 'status' => 'draft', 'total' => '50000', 'created_by' => $user->id]);
        $purchaseOrder->items()->create(['product_id' => $product->id, 'ordered_quantity' => '100', 'received_quantity' => 0, 'unit_price' => '500', 'line_total' => '50000']);
        $supplier->update(['status' => 'inactive']);

        $response = $this->actingAs($user)
            ->from(route('purchasing.index'))
            ->post(route('purchasing.approve', $purchaseOrder));

        $response->assertRedirect(route('purchasing.index'));
        $response->assertSessionHasErrors([
            'supplier_id' => 'Supplier tidak aktif atau tidak tersedia.',
        ]);
        $this->assertSame('draft', $purchaseOrder->fresh()->status);
    }

    #[DataProvider('purchaseOrderApprovalRoles')]
    public function test_owner_and_super_administrator_can_approve_purchase_orders(UserRole $role): void
    {
        $approver = User::factory()->create(['role' => $role]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-APPROVAL-'.$role->value,
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
            'total' => '50000',
            'created_by' => $approver->id,
        ]);
        $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => '100',
            'received_quantity' => 0,
            'unit_price' => '500',
            'line_total' => '50000',
        ]);

        $response = $this->actingAs($approver)->post(route('purchasing.approve', $purchaseOrder));

        $response->assertRedirect();
        $this->assertSame('approved', $purchaseOrder->fresh()->status);
        $this->assertSame($approver->id, $purchaseOrder->fresh()->approved_by);
    }

    #[DataProvider('purchaseOrderNonApprovalRoles')]
    public function test_roles_other_than_owner_and_super_administrator_cannot_approve_purchase_orders(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-APPROVAL-FORBIDDEN-'.$role->value,
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'draft',
            'total' => '50000',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('purchasing.approve', $purchaseOrder))
            ->assertForbidden();

        $this->assertSame('draft', $purchaseOrder->fresh()->status);
    }

    public function test_warehouse_can_upload_proof_and_post_a_goods_receipt(): void
    {
        Storage::fake('local');
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-WAREHOUSE-RECEIPT',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'status' => 'approved',
            'total' => '50000',
            'created_by' => $warehouseUser->id,
        ]);
        $item = $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => '100',
            'received_quantity' => 0,
            'unit_price' => '500',
            'line_total' => '50000',
        ]);

        $response = $this->actingAs($warehouseUser)->post(route('purchasing.receive.store', $purchaseOrder), [
            'quantities' => [$item->id => '80'],
            'proof' => UploadedFile::fake()->image('bukti-penerimaan.jpg'),
        ]);

        $receipt = $purchaseOrder->goodsReceipts()->firstOrFail();
        $response->assertRedirect(route('purchasing.index'));
        $this->assertNotNull($receipt->proof_path);
        Storage::disk('local')->assertExists($receipt->proof_path);
        $this->actingAs($warehouseUser)
            ->get(route('purchasing.receipts.proof', $receipt))
            ->assertOk();
        $this->assertSame('80.000', StockBalance::query()->where('product_id', $product->id)->value('quantity'));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $warehouseUser->id,
            'action' => 'RECEIVE',
            'module' => 'GoodsReceipt',
            'entity_id' => $receipt->id,
        ]);
    }

    public function test_goods_receipt_requires_an_image_proof(): void
    {
        Storage::fake('local');
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-RECEIPT-WITHOUT-PROOF',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'approved',
            'total' => '50000',
            'created_by' => $warehouseUser->id,
        ]);

        $response = $this->actingAs($warehouseUser)
            ->from(route('purchasing.receive.create', $purchaseOrder))
            ->post(route('purchasing.receive.store', $purchaseOrder), ['quantities' => []]);

        $response->assertRedirect(route('purchasing.receive.create', $purchaseOrder));
        $response->assertSessionHasErrors([
            'proof' => 'Bukti penerimaan barang wajib diunggah.',
        ]);
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_admin_cannot_post_goods_receipts(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-ADMIN-RECEIPT-FORBIDDEN',
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'approved',
            'total' => '50000',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('purchasing.receive.store', $purchaseOrder), [
                'quantities' => [],
                'proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[DataProvider('goodsReceiptRoles')]
    public function test_warehouse_owner_and_super_administrator_can_open_goods_receipt_page(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-RECEIPT-PAGE-'.$role->value,
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'order_date' => today(),
            'status' => 'approved',
            'total' => '50000',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('purchasing.receive.create', $purchaseOrder))
            ->assertOk()
            ->assertSeeText('Bukti Penerimaan Barang');
    }

    /** @return array<string, array{UserRole}> */
    public static function purchaseOrderApprovalRoles(): array
    {
        return [
            'owner' => [UserRole::Owner],
            'super administrator' => [UserRole::SuperAdministrator],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function purchaseOrderNonApprovalRoles(): array
    {
        return [
            'admin' => [UserRole::Admin],
            'warehouse' => [UserRole::Warehouse],
            'sales' => [UserRole::Sales],
        ];
    }

    /** @return array<string, array{UserRole}> */
    public static function goodsReceiptRoles(): array
    {
        return [
            'warehouse' => [UserRole::Warehouse],
            'owner' => [UserRole::Owner],
            'super administrator' => [UserRole::SuperAdministrator],
        ];
    }
}
