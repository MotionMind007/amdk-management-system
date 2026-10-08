<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_production_user_can_view_production_but_not_sales_or_purchasing(): void
    {
        $user = User::factory()->create(['role' => UserRole::Production]);

        $this->actingAs($user)->get('/production')->assertOk();
        $this->actingAs($user)->get('/sales')->assertForbidden();
        $this->actingAs($user)->get('/purchasing')->assertForbidden();
    }

    public function test_manager_can_view_transactions_but_cannot_create_them(): void
    {
        $user = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($user)->get('/sales')->assertOk();
        $this->actingAs($user)->get('/sales/create')->assertForbidden();
        $this->actingAs($user)->get('/reports')->assertOk();
    }
}
