<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashReconciliationService
{
    public function __construct(private DocumentNumberService $numbers, private AccountingPeriodService $periods) {}

    public function create(CashAccount $account, string $date, string $statementBalance, ?string $notes, User $user): CashReconciliation
    {
        return DB::transaction(function () use ($account, $date, $statementBalance, $notes, $user): CashReconciliation {
            $this->periods->ensureOpen($date);
            $account = CashAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();

            return CashReconciliation::query()->create([
                'number' => $this->numbers->next('RECON'),
                'cash_account_id' => $account->id,
                'reconciliation_date' => $date,
                'system_balance' => $account->balance,
                'statement_balance' => $statementBalance,
                'difference' => bcsub($statementBalance, $account->balance, 2),
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
        });
    }
}
