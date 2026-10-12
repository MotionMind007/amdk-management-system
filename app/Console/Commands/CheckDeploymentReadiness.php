<?php

namespace App\Console\Commands;

use App\Services\DeploymentReadinessService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:check-deployment {--strict : Perlakukan peringatan sebagai kegagalan}')]
#[Description('Periksa keamanan konfigurasi dan kesiapan runtime sebelum deployment production.')]
class CheckDeploymentReadiness extends Command
{
    public function handle(DeploymentReadinessService $readiness): int
    {
        $result = $readiness->check();

        $this->table(
            ['Pemeriksaan', 'Status', 'Keterangan'],
            collect($result['checks'])->map(fn (array $check): array => [
                $check['name'],
                match ($check['status']) {
                    'pass' => 'OK',
                    'warning' => 'PERINGATAN',
                    default => 'GAGAL',
                },
                $check['message'],
            ])->all(),
        );

        if (! $result['ready'] || ($this->option('strict') && $result['warnings'] > 0)) {
            $this->error("Deployment belum siap: {$result['failures']} kegagalan, {$result['warnings']} peringatan.");

            return self::FAILURE;
        }

        $this->info("Pemeriksaan deployment lulus dengan {$result['warnings']} peringatan.");

        return self::SUCCESS;
    }
}
