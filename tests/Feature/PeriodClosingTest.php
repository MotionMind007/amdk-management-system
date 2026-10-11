<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AccountingPeriodService;
use App\Services\PaymentService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodClosingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_closed_period_rejects_payment_and_can_be_reopened(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $period = app(AccountingPeriodService::class)->close('2026-10', 'Tutup bulan', $owner);
        $sale = Sale::query()->create(['number' => 'INV-PERIOD', 'customer_id' => Customer::factory()->create()->id, 'warehouse_id' => Warehouse::factory()->create()->id, 'sale_date' => '2026-10-01', 'status' => 'unpaid', 'total' => 100000, 'outstanding_amount' => 100000, 'created_by' => $owner->id]);
        $account = CashAccount::query()->create(['code' => 'KAS-P', 'name' => 'Kas', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);

        try {
            app(PaymentService::class)->receiveCustomerPayment($sale, $account, '10000', $owner, '2026-10-11');
            $this->fail('Periode tertutup seharusnya menolak pembayaran.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('date', $exception->errors());
        }

        app(AccountingPeriodService::class)->reopen($period, $owner);
        app(PaymentService::class)->receiveCustomerPayment($sale, $account, '10000', $owner, '2026-10-11');
        $this->assertDatabaseHas('customer_payments', ['sale_id' => $sale->id, 'amount' => 10000]);
    }

    public function test_only_owner_or_super_admin_can_manage_periods(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($admin)->get(route('finance.periods.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('finance.periods.store'), ['period' => '2026-09'])->assertRedirect();
        $this->assertDatabaseHas(AccountingPeriod::class, ['period' => '2026-09', 'status' => 'closed']);
    }
}
