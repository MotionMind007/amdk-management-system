<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemHealthAlert;
use App\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemMonitoringService
{
    public function __construct(
        private SystemHealthService $health,
        private DatabaseBackupService $backups,
    ) {}

    /**
     * @return array{healthy: bool, checks: list<array{key: string, name: string, healthy: bool, message: string}>}
     */
    public function check(): array
    {
        $health = $this->health->check();
        $checks = [
            $this->result('database', 'Koneksi Database', $health['checks']['database']['healthy'], $health['checks']['database']['message']),
            $this->result('storage', 'Storage Aplikasi', $health['checks']['storage']['healthy'], $health['checks']['storage']['message']),
            $this->backup(),
            $this->disk(),
            $this->failedJobs(),
        ];

        return ['healthy' => collect($checks)->every(fn (array $check): bool => $check['healthy']), 'checks' => $checks];
    }

    /**
     * @return array{healthy: bool, new_alerts: int, recovered_alerts: int, checks: list<array{key: string, name: string, healthy: bool, message: string}>}
     */
    public function monitor(): array
    {
        $result = $this->check();
        $newAlerts = 0;
        $recoveredAlerts = 0;

        foreach ($result['checks'] as $check) {
            $cacheKey = 'system-monitor.alert.'.$check['key'];
            $previousMessage = Cache::get($cacheKey);

            if (! $check['healthy']) {
                if ($previousMessage !== $check['message']) {
                    $this->notify($check, false);
                    Cache::put($cacheKey, $check['message'], now()->addDays(7));
                    $newAlerts++;
                }

                continue;
            }

            if (is_string($previousMessage)) {
                $this->notify($check, true);
                Cache::forget($cacheKey);
                $recoveredAlerts++;
            }
        }

        return [...$result, 'new_alerts' => $newAlerts, 'recovered_alerts' => $recoveredAlerts];
    }

    /** @return array{key: string, name: string, healthy: bool, message: string} */
    private function backup(): array
    {
        try {
            $backup = collect($this->backups->all())->first();
            if ($backup === null) {
                return $this->result('backup', 'Backup Database', false, 'Belum ada backup database yang tersedia.');
            }

            $maximumAge = max(1, (int) config('operations.system_monitor.backup_max_age_hours', 30));
            $ageInHours = (int) floor((now()->timestamp - $backup['created_at']) / 3600);

            if ($backup['verified_at'] === null) {
                return $this->result('backup', 'Backup Database', false, "Backup {$backup['filename']} belum terverifikasi restore.");
            }

            if (! $backup['offsite_exists']) {
                return $this->result('backup', 'Backup Database', false, "Salinan R2 untuk backup {$backup['filename']} tidak ditemukan.");
            }

            return $this->result(
                'backup',
                'Backup Database',
                $ageInHours <= $maximumAge,
                $ageInHours <= $maximumAge
                    ? "Backup terakhir {$ageInHours} jam lalu dan tersalin ke R2."
                    : "Backup terakhir {$ageInHours} jam lalu, melewati batas {$maximumAge} jam.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('backup', 'Backup Database', false, 'Status backup atau R2 tidak dapat diperiksa.');
        }
    }

    /** @return array{key: string, name: string, healthy: bool, message: string} */
    private function disk(): array
    {
        $total = disk_total_space(storage_path());
        $free = disk_free_space(storage_path());

        if (! is_float($total) && ! is_int($total) || ! is_float($free) && ! is_int($free) || $total <= 0) {
            return $this->result('disk', 'Kapasitas Disk', false, 'Kapasitas disk server tidak dapat diperiksa.');
        }

        $freePercent = (int) floor(($free / $total) * 100);
        $threshold = min(99, max(1, (int) config('operations.system_monitor.disk_free_percent_threshold', 15)));

        return $this->result(
            'disk',
            'Kapasitas Disk',
            $freePercent >= $threshold,
            "Sisa kapasitas disk {$freePercent}% (batas minimum {$threshold}%).",
        );
    }

    /** @return array{key: string, name: string, healthy: bool, message: string} */
    private function failedJobs(): array
    {
        try {
            if (! Schema::hasTable('failed_jobs')) {
                return $this->result('queue', 'Queue', false, 'Tabel failed jobs tidak tersedia.');
            }

            $failedJobs = DB::table('failed_jobs')->count();

            return $this->result(
                'queue',
                'Queue',
                $failedJobs === 0,
                $failedJobs === 0 ? 'Tidak ada queue yang gagal.' : "Terdapat {$failedJobs} queue gagal yang perlu ditangani.",
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->result('queue', 'Queue', false, 'Status queue tidak dapat diperiksa.');
        }
    }

    /** @param array{key: string, name: string, healthy: bool, message: string} $check */
    private function notify(array $check, bool $recovered): void
    {
        try {
            $recipients = User::query()
                ->where('is_active', true)
                ->whereIn('role', [UserRole::Owner, UserRole::SuperAdministrator])
                ->get();

            foreach ($recipients as $recipient) {
                $recipient->notify(new SystemHealthAlert($check['name'], $check['message'], $recovered));
            }

            Log::channel(config('logging.default'))->warning('System health monitoring alert.', [
                'check' => $check['key'],
                'healthy' => $check['healthy'],
                'message' => $check['message'],
                'recovered' => $recovered,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array{key: string, name: string, healthy: bool, message: string} */
    private function result(string $key, string $name, bool $healthy, string $message): array
    {
        return compact('key', 'name', 'healthy', 'message');
    }
}
