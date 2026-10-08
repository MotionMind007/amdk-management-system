<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_create_user_with_role(): void
    {
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->post('/admin/users', [
            'name' => 'Operator Produksi',
            'email' => 'produksi@example.com',
            'role' => UserRole::Production->value,
            'is_active' => true,
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'produksi@example.com',
            'role' => UserRole::Production->value,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'CREATE', 'module' => 'User']);
    }

    public function test_non_administrator_cannot_view_user_management_or_audit_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/admin/audit-logs')->assertForbidden();
    }
}
