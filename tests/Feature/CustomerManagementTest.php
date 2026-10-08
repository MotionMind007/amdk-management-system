<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_user_can_create_customer_and_activity_is_audited(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', [
            'customer_code' => 'CUS-001',
            'name' => 'Toko Papua Jaya',
            'phone' => '08123456789',
            'email' => 'papua@example.com',
            'address' => 'Jayapura',
            'credit_limit' => '5000000',
            'payment_term' => 14,
            'status' => 'active',
            'notes' => null,
        ]);

        $customer = Customer::query()->where('customer_code', 'CUS-001')->firstOrFail();
        $response->assertRedirect(route('customers.index'));
        $this->assertSame('Toko Papua Jaya', $customer->name);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'CREATE',
            'module' => 'Customer',
            'entity_id' => $customer->id,
        ]);
    }

    public function test_warehouse_user_cannot_access_customer_management(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse]);

        $this->actingAs($user)->get('/customers')->assertForbidden();
    }

    public function test_duplicate_customer_code_is_rejected_with_clear_message(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['customer_code' => 'CUS-001']);

        $response = $this->actingAs($user)->from('/customers/create')->post('/customers', [
            'customer_code' => 'CUS-001',
            'name' => 'Pelanggan Lain',
            'credit_limit' => 0,
            'payment_term' => 0,
            'status' => 'active',
        ]);

        $response->assertRedirect('/customers/create');
        $response->assertSessionHasErrors(['customer_code' => 'Kode pelanggan sudah digunakan.']);
        $this->assertDatabaseCount('customers', 1);
    }
}
