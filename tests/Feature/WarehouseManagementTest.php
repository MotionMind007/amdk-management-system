<?php

namespace Tests\Feature;

use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_warehouse_with_normalized_code_and_audit_log(): void
    {
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->post(route('warehouses.store'), [
            'code' => ' gdg-timur ',
            'name' => 'Gudang Timur',
            'address' => 'Jayapura',
            'is_active' => '1',
        ]);

        $warehouse = Warehouse::query()->where('code', 'GDG-TIMUR')->firstOrFail();
        $response->assertRedirect(route('warehouses.index'));
        $this->assertSame('Gudang Timur', $warehouse->name);
        $this->assertTrue($warehouse->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE',
            'module' => 'Warehouse',
            'entity_id' => $warehouse->id,
            'description' => 'Menambahkan gudang GDG-TIMUR - Gudang Timur dengan status aktif.',
        ]);
    }

    public function test_administrator_can_deactivate_a_warehouse_without_removing_its_stock_history(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['code' => 'GDG-LAMA']);
        Warehouse::factory()->create(['code' => 'GDG-AKTIF', 'is_active' => true]);
        $stockBalance = StockBalance::factory()->create([
            'warehouse_id' => $warehouse->id,
            'quantity' => 125,
        ]);

        $response = $this->actingAs($administrator)->put(route('warehouses.update', $warehouse), [
            'code' => 'GDG-LAMA',
            'name' => 'Gudang Lama',
            'address' => 'Lokasi lama',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('warehouses.index'));
        $this->assertFalse($warehouse->fresh()->is_active);
        $this->assertModelExists($stockBalance);
        $this->assertSame('125.000', $stockBalance->fresh()->quantity);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE',
            'module' => 'Warehouse',
            'entity_id' => $warehouse->id,
        ]);
    }

    public function test_last_active_warehouse_cannot_be_deactivated(): void
    {
        $administrator = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['is_active' => true]);

        $response = $this->actingAs($administrator)
            ->from(route('warehouses.edit', $warehouse))
            ->put(route('warehouses.update', $warehouse), [
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'is_active' => '0',
            ]);

        $response->assertRedirect(route('warehouses.edit', $warehouse));
        $response->assertSessionHasErrors([
            'is_active' => 'Minimal satu gudang harus tetap aktif.',
        ]);
        $this->assertTrue($warehouse->fresh()->is_active);
    }

    public function test_duplicate_warehouse_code_is_rejected_after_normalization(): void
    {
        $administrator = User::factory()->administrator()->create();
        Warehouse::factory()->create(['code' => 'GDG-TIMUR']);

        $response = $this->actingAs($administrator)
            ->from(route('warehouses.create'))
            ->post(route('warehouses.store'), [
                'code' => 'gdg-timur',
                'name' => 'Gudang Duplikat',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('warehouses.create'));
        $response->assertSessionHasErrors([
            'code' => 'Kode gudang sudah digunakan.',
        ]);
        $this->assertDatabaseCount('warehouses', 1);
    }

    public function test_inventory_user_can_view_warehouses_but_cannot_manage_them(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Utama']);

        $this->actingAs($warehouseUser)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSeeText('Gudang Utama')
            ->assertDontSeeText('+ Tambah Gudang');
        $this->actingAs($warehouseUser)->get(route('warehouses.create'))->assertForbidden();
        $this->actingAs($warehouseUser)->get(route('warehouses.edit', $warehouse))->assertForbidden();
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_roles_cannot_store_warehouses(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->post(route('warehouses.store'), [
            'code' => 'GDG-BARU',
            'name' => 'Gudang Baru',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseCount('warehouses', 0);
    }

    /** @return array<string, array{UserRole}> */
    public static function nonAdministratorRoles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            if (! in_array($role, [UserRole::SuperAdministrator, UserRole::Owner], true)) {
                $roles[$role->value] = [$role];
            }
        }

        return $roles;
    }
}
