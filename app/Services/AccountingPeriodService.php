<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function __construct(private OperationalIntegrityService $integrity) {}

    public function ensureOpen(CarbonInterface|string $date): void
    {
        $period = date_create((string) $date)->format('Y-m');

        if (AccountingPeriod::query()->where('period', $period)->where('status', 'closed')->exists()) {
            throw ValidationException::withMessages(['date' => "Periode {$period} sudah ditutup. Transaksi pada periode ini tidak dapat diposting atau diubah."]);
        }
    }

    public function close(string $period, ?string $notes, User $user): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $notes, $user): AccountingPeriod {
            $integrity = $this->integrity->check();

            if (! $integrity['healthy']) {
                $issues = collect($integrity['checks'])
                    ->reject(fn (array $check): bool => $check['healthy'])
                    ->pluck('message')
                    ->implode(' ');

                throw ValidationException::withMessages([
                    'period' => "Periode belum dapat ditutup karena integritas data bermasalah: {$issues}",
                ]);
            }

            $startDate = CarbonImmutable::createFromFormat('Y-m-d', "{$period}-01")->startOfMonth();
            $endDate = $startDate->endOfMonth();
            $pendingDocuments = collect([
                'penjualan' => DB::table('sales')->whereBetween('sale_date', [$startDate, $endDate])->where('status', 'draft')->count(),
                'produksi' => DB::table('daily_productions')->whereBetween('production_date', [$startDate, $endDate])->where('status', 'draft')->count(),
                'stok awal' => DB::table('opening_stocks')->whereBetween('stock_date', [$startDate, $endDate])->where('status', 'draft')->count(),
                'stok opname' => DB::table('stock_opnames')->whereBetween('opname_date', [$startDate, $endDate])->where('status', 'draft')->count(),
                'transfer stok' => DB::table('inventory_transfers')->whereBetween('transfer_date', [$startDate, $endDate])->where('status', 'draft')->count(),
            ])->filter(fn (int $count): bool => $count > 0);

            if ($pendingDocuments->isNotEmpty()) {
                $summary = $pendingDocuments
                    ->map(fn (int $count, string $label): string => "{$count} {$label}")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'period' => "Selesaikan dokumen draft periode {$period} terlebih dahulu: {$summary}.",
                ]);
            }

            return AccountingPeriod::query()->updateOrCreate(
                ['period' => $period],
                ['status' => 'closed', 'notes' => $notes, 'closed_by' => $user->id, 'closed_at' => now(), 'reopened_by' => null, 'reopened_at' => null],
            );
        });
    }

    public function reopen(AccountingPeriod $accountingPeriod, User $user): AccountingPeriod
    {
        $accountingPeriod->update(['status' => 'open', 'reopened_by' => $user->id, 'reopened_at' => now()]);

        return $accountingPeriod;
    }
}
