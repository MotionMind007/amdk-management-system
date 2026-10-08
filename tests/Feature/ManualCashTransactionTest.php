<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ManualCashTransactionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_finance_user_can_record_manual_income(): void
    {
        $user = User::factory()->administrator()->create();
        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'balance' => 0, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('finance.transactions.store'), [
            'cash_account_id' => $account->id,
            'transaction_date' => '2026-10-07',
            'direction' => 'in',
            'amount' => 250000,
            'description' => 'Pendapatan lain',
        ]);

        $response->assertRedirect(route('finance.index'));
        $this->assertSame('250000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('cash_transactions', ['direction' => 'in', 'amount' => 250000]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_manual_expense_cannot_make_cash_negative(): void
    {
        $user = User::factory()->administrator()->create();
        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'balance' => 10000, 'is_active' => true]);

        $response = $this->actingAs($user)->from(route('finance.transactions.create'))->post(route('finance.transactions.store'), [
            'cash_account_id' => $account->id,
            'transaction_date' => '2026-10-07',
            'direction' => 'out',
            'amount' => 15000,
            'description' => 'Biaya',
        ]);

        $response->assertRedirect(route('finance.transactions.create'))->assertSessionHasErrors('amount');
        $this->assertSame('10000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 0);
    }
}
