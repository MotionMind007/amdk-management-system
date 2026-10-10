<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_finance_summary_shows_cash_office_and_bank_balances(): void
    {
        $user = User::factory()->administrator()->create();
        CashAccount::create(['code' => 'KAS', 'name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 3000000, 'is_active' => true]);
        CashAccount::create(['code' => 'BANK-TEST', 'name' => 'Bank', 'type' => 'bank', 'balance' => 1250000, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('finance.index'));

        $response->assertOk()->assertSee([
            'Kas Kantor',
            'Rp 3.000.000',
            'Bank',
            'Rp 1.250.000',
        ]);
    }
}
