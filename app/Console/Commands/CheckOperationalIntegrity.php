<?php

namespace App\Console\Commands;

use App\Services\OperationalIntegrityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:check-integrity')]
#[Description('Periksa konsistensi saldo stok, piutang, hutang, serta kas dan bank.')]
class CheckOperationalIntegrity extends Command
{
    public function handle(OperationalIntegrityService $integrity): int
    {
        $result = $integrity->check();

        $this->table(
            ['Area', 'Status', 'Temuan', 'Keterangan'],
            collect($result['checks'])->map(fn (array $check, string $area): array => [
                str($area)->replace('_', ' ')->title()->toString(),
                $check['healthy'] ? 'OK' : 'GAGAL',
                $check['issues'],
                $check['message'],
            ])->values()->all(),
        );

        if (! $result['healthy']) {
            $this->error('Ditemukan ketidaksesuaian data operasional.');

            return self::FAILURE;
        }

        $this->info('Seluruh pemeriksaan konsistensi data lulus.');

        return self::SUCCESS;
    }
}
