<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function ensureOpen(CarbonInterface|string $date): void
    {
        $period = date_create((string) $date)->format('Y-m');

        if (AccountingPeriod::query()->where('period', $period)->where('status', 'closed')->exists()) {
            throw ValidationException::withMessages(['date' => "Periode {$period} sudah ditutup. Transaksi pada periode ini tidak dapat diposting atau diubah."]);
        }
    }

    public function close(string $period, ?string $notes, User $user): AccountingPeriod
    {
        return AccountingPeriod::query()->updateOrCreate(
            ['period' => $period],
            ['status' => 'closed', 'notes' => $notes, 'closed_by' => $user->id, 'closed_at' => now(), 'reopened_by' => null, 'reopened_at' => null],
        );
    }

    public function reopen(AccountingPeriod $accountingPeriod, User $user): AccountingPeriod
    {
        $accountingPeriod->update(['status' => 'open', 'reopened_by' => $user->id, 'reopened_at' => now()]);

        return $accountingPeriod;
    }
}
