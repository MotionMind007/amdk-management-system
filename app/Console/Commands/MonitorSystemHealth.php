<?php

namespace App\Console\Commands;

use App\Services\SystemMonitoringService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:monitor-health')]
#[Description('Pantau backup, kapasitas disk, queue, database, dan storage aplikasi.')]
class MonitorSystemHealth extends Command
{
    public function handle(SystemMonitoringService $monitoring): int
    {
        $result = $monitoring->monitor();

        $this->table(
            ['Pemeriksaan', 'Status', 'Keterangan'],
            collect($result['checks'])->map(fn (array $check): array => [
                $check['name'],
                $check['healthy'] ? 'OK' : 'GAGAL',
                $check['message'],
            ])->all(),
        );

        $this->info("Notifikasi baru: {$result['new_alerts']}. Notifikasi pemulihan: {$result['recovered_alerts']}.");

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
