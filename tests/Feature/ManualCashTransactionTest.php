<?php

namespace Tests\Feature;

use App\ExpenseCategory;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ManualCashTransactionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expense_form_shows_supported_categories(): void
    {
        $user = User::factory()->administrator()->create();
        CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'balance' => 500000, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('finance.transactions.create'));

        $response->assertOk()->assertSee([
            'Kategori Pengeluaran',
            'Operasional',
            'Gaji / Upah',
            'Listrik',
            'BBM / Transportasi',
            'Pemeliharaan Mesin',
        ]);
    }

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
            'expense_category' => ExpenseCategory::Operational->value,
            'amount' => 15000,
            'description' => 'Biaya',
        ]);

        $response->assertRedirect(route('finance.transactions.create'))->assertSessionHasErrors('amount');
        $this->assertSame('10000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_finance_user_can_record_categorized_manual_expense(): void
    {
        $user = User::factory()->administrator()->create();
        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'balance' => 500000, 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('finance.transactions.store'), [
            'cash_account_id' => $account->id,
            'transaction_date' => '2026-10-08',
            'direction' => 'out',
            'expense_category' => ExpenseCategory::Electricity->value,
            'amount' => 150000,
            'description' => 'Tagihan listrik pabrik',
        ]);

        $response->assertRedirect(route('finance.index'));
        $this->assertSame('350000.00', $account->fresh()->balance);
        $this->assertDatabaseHas('cash_transactions', [
            'direction' => 'out',
            'expense_category' => ExpenseCategory::Electricity->value,
            'amount' => 150000,
        ]);
        $auditLog = AuditLog::query()->sole();
        $this->assertStringContainsString('Pengeluaran Rp 150.000 melalui Kas', $auditLog->description);
        $this->assertStringContainsString('Kategori: Listrik', $auditLog->description);
        $this->assertStringContainsString('Keterangan: Tagihan listrik pabrik', $auditLog->description);

        $this->actingAs($user)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('Pengeluaran Rp 150.000 melalui Kas')
            ->assertSeeText('Tagihan listrik pabrik');
    }

    public function test_manual_expense_requires_a_category(): void
    {
        $user = User::factory()->administrator()->create();
        $account = CashAccount::create(['code' => 'KAS', 'name' => 'Kas', 'type' => 'cash', 'balance' => 500000, 'is_active' => true]);

        $response = $this->actingAs($user)->from(route('finance.transactions.create'))->post(route('finance.transactions.store'), [
            'cash_account_id' => $account->id,
            'transaction_date' => '2026-10-08',
            'direction' => 'out',
            'amount' => 150000,
            'description' => 'Tagihan listrik pabrik',
        ]);

        $response->assertRedirect(route('finance.transactions.create'))->assertSessionHasErrors('expense_category');
        $this->assertSame('500000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_manual_transaction_rejects_an_inactive_cash_account(): void
    {
        $user = User::factory()->administrator()->create();
        $account = CashAccount::create(['code' => 'NONAKTIF', 'name' => 'Kas Lama', 'type' => 'cash', 'balance' => 500000, 'is_active' => false]);

        $response = $this->actingAs($user)
            ->from(route('finance.transactions.create'))
            ->post(route('finance.transactions.store'), [
                'cash_account_id' => $account->id,
                'transaction_date' => '2026-10-08',
                'direction' => 'in',
                'amount' => 150000,
                'description' => 'Pendapatan lain',
            ]);

        $response->assertRedirect(route('finance.transactions.create'));
        $response->assertSessionHasErrors('cash_account_id');
        $this->assertSame('500000.00', $account->fresh()->balance);
        $this->assertDatabaseCount('cash_transactions', 0);
    }
}
