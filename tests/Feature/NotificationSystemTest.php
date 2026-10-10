<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\GoodsReceiptExpected;
use App\Notifications\PurchaseOrderAwaitingApproval;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_purchase_order_notifies_only_active_owner_and_super_administrator(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin Pembelian']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $superAdministrator = User::factory()->create(['role' => UserRole::SuperAdministrator]);
        $inactiveOwner = User::factory()->inactive()->create(['role' => UserRole::Owner]);
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $supplier = Supplier::factory()->create(['name' => 'Supplier Kemasan']);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->post(route('purchasing.store'), [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-10-10',
            'expected_date' => '2026-10-12',
            'payment_type' => 'credit',
            'due_date' => '2026-10-24',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_price' => 500],
            ],
        ]);

        $response->assertRedirect(route('purchasing.index'));
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(1, $superAdministrator->notifications()->count());
        $this->assertSame(0, $inactiveOwner->notifications()->count());
        $this->assertSame(0, $warehouseUser->notifications()->count());
        $this->assertSame('PO Baru Menunggu Persetujuan', $owner->notifications()->firstOrFail()->data['title']);
        $this->assertStringContainsString('Supplier Kemasan', $owner->notifications()->firstOrFail()->data['message']);
    }

    public function test_approved_purchase_order_notifies_only_active_warehouse_users(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner, 'name' => 'Direktur']);
        $firstWarehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $secondWarehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $inactiveWarehouseUser = User::factory()->inactive()->create(['role' => UserRole::Warehouse]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supplier = Supplier::factory()->create(['name' => 'Supplier Tutup Botol']);
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $purchaseOrder = PurchaseOrder::create([
            'number' => 'PO-NOTIF-APPROVED',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => '2026-10-10',
            'expected_date' => '2026-10-12',
            'status' => 'draft',
            'total' => '50000',
            'created_by' => $admin->id,
        ]);
        $purchaseOrder->items()->create([
            'product_id' => $product->id,
            'ordered_quantity' => '100',
            'received_quantity' => 0,
            'unit_price' => '500',
            'line_total' => '50000',
        ]);

        $response = $this->actingAs($owner)->post(route('purchasing.approve', $purchaseOrder));

        $response->assertRedirect();
        $this->assertSame(1, $firstWarehouseUser->notifications()->count());
        $this->assertSame(1, $secondWarehouseUser->notifications()->count());
        $this->assertSame(0, $inactiveWarehouseUser->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
        $notification = $firstWarehouseUser->notifications()->firstOrFail();
        $this->assertSame('Barang Masuk Menunggu Penerimaan', $notification->data['title']);
        $this->assertStringContainsString('12/10/2026', $notification->data['message']);
    }

    public function test_user_can_view_and_mark_own_notification_as_read_but_cannot_access_another_users_notification(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $otherOwner = User::factory()->create(['role' => UserRole::Owner]);
        $owner->notify(new PurchaseOrderAwaitingApproval(10, 'PO-NOTIF-001', '<script>Supplier</script>', 'Admin', '50000'));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('PO Baru Menunggu Persetujuan')
            ->assertSeeText('1 belum dibaca')
            ->assertSee('&lt;script&gt;Supplier&lt;/script&gt;', false)
            ->assertDontSee('<script>Supplier</script>', false);

        $this->actingAs($otherOwner)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($owner)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('purchasing.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouseUser->notify(new GoodsReceiptExpected(10, 'PO-001', 'Supplier Satu', 'Owner', null));
        $warehouseUser->notify(new GoodsReceiptExpected(11, 'PO-002', 'Supplier Dua', 'Owner', '15/10/2026'));

        $this->assertSame(2, $warehouseUser->unreadNotifications()->count());

        $this->actingAs($warehouseUser)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $warehouseUser->unreadNotifications()->count());
    }
}
