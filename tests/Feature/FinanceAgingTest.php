<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FinanceAgingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_aging_page_groups_outstanding_documents(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create(['name' => 'Toko Aging']);
        Sale::query()->create(['number' => 'INV-AGING', 'customer_id' => $customer->id, 'warehouse_id' => Warehouse::factory()->create()->id, 'sale_date' => today()->subDays(45), 'due_date' => today()->subDays(35), 'status' => 'unpaid', 'total' => 250000, 'outstanding_amount' => 250000, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('finance.aging'))->assertOk()->assertSee('31–60 hari')->assertSee('250.000')->assertSee('Toko Aging');
    }

    public function test_due_reminder_command_is_idempotent_per_day(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create();
        Sale::query()->create(['number' => 'INV-DUE', 'customer_id' => $customer->id, 'warehouse_id' => Warehouse::factory()->create()->id, 'sale_date' => today(), 'due_date' => today()->addDay(), 'status' => 'unpaid', 'total' => 10000, 'outstanding_amount' => 10000, 'created_by' => $admin->id]);

        $this->artisan('finance:send-due-reminders')->assertSuccessful();
        $this->artisan('finance:send-due-reminders')->assertSuccessful();
        Notification::assertCount(1);
    }
}
