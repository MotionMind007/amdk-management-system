<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_warehouse_user_can_manage_production_inventory_and_goods_receipts_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse]);

        $this->actingAs($user)->get('/production')->assertOk();
        $this->actingAs($user)->get('/production/create')->assertOk();
        $this->actingAs($user)->get('/inventory')->assertOk();
        $this->actingAs($user)->get('/purchasing')->assertOk();
        $this->actingAs($user)->get('/purchasing/create')->assertForbidden();
        $this->actingAs($user)->get('/sales')->assertForbidden();
    }

    public function test_admin_can_access_operational_modules_except_production_and_system_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->get('/sales')->assertOk();
        $this->actingAs($user)->get('/purchasing')->assertOk();
        $this->actingAs($user)->get('/inventory')->assertOk();
        $this->actingAs($user)->get('/finance')->assertOk();
        $this->actingAs($user)->get('/reports')->assertOk();
        $this->actingAs($user)->get('/production')->assertForbidden();
        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_sales_user_can_access_sales_and_stock_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($user)->get('/sales')->assertOk();
        $this->actingAs($user)->get('/inventory')->assertOk();
        $this->actingAs($user)->get('/production')->assertForbidden();
        $this->actingAs($user)->get('/purchasing')->assertForbidden();
        $this->actingAs($user)->get('/finance')->assertForbidden();
    }

    #[DataProvider('fullAccessRoles')]
    public function test_owner_and_super_administrator_have_full_access(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/sales')->assertOk();
        $this->actingAs($user)->get('/sales/create')->assertOk();
        $this->actingAs($user)->get('/production/create')->assertOk();
        $this->actingAs($user)->get('/admin/users')->assertOk();
        $this->actingAs($user)->get('/reports')->assertOk();
    }

    /** @return array<string, array{UserRole}> */
    public static function fullAccessRoles(): array
    {
        return [
            'owner' => [UserRole::Owner],
            'super administrator' => [UserRole::SuperAdministrator],
        ];
    }
}
