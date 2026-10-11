<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use App\Services\CashReconciliationService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CashReconciliationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reconciliation_records_snapshot_and_does_not_change_account_balance(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $account = CashAccount::query()->create(['code' => 'BANK-T', 'name' => 'Bank Test', 'type' => 'bank', 'balance' => 150000, 'is_active' => true]);
        $reconciliation = app(CashReconciliationService::class)->create($account, today()->toDateString(), '147500', 'Biaya bank belum dicatat', $user);

        $this->assertSame('150000.00', $reconciliation->system_balance);
        $this->assertSame('-2500.00', $reconciliation->difference);
        $this->assertSame('150000.00', $account->fresh()->balance);
    }
}
