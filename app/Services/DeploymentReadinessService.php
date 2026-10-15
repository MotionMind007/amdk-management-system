<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

class DeploymentReadinessService
{
    public function __construct(
        private SystemHealthService $health,
        private Migrator $migrator,
        private Schedule $schedule,
        private ExecutableFinder $executableFinder,
    ) {}

    /** @return array{ready: bool, checks: list<array{name: string, status: string, message: string}>, failures: int, warnings: int} */
    public function check(): array
    {
        $checks = [
            $this->result('Environment', app()->isProduction(), 'production', 'APP_ENV harus production.'),
            $this->result('Debug mode', ! (bool) config('app.debug'), 'Nonaktif', 'APP_DEBUG harus false.'),
            $this->result('URL HTTPS', parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https', (string) config('app.url'), 'APP_URL harus menggunakan HTTPS.'),
            $this->result('Application key', $this->hasValidApplicationKey(), 'Kunci enkripsi valid.', 'APP_KEY kosong atau tidak valid.'),
            $this->result('Zona waktu', config('app.timezone') === 'Asia/Jayapura', (string) config('app.timezone'), 'APP_TIMEZONE harus Asia/Jayapura.'),
            $this->result('Database', config('database.default') === 'pgsql', (string) config('database.default'), 'Production harus menggunakan PostgreSQL.'),
            ...$this->healthChecks(),
            $this->migrationCheck(),
            ...$this->runtimeDriverChecks(),
            $this->result('Session cookie', (bool) config('session.secure') && (bool) config('session.http_only'), 'Secure dan HttpOnly aktif.', 'SESSION_SECURE_COOKIE dan SESSION_HTTP_ONLY harus true.'),
            $this->result('SameSite cookie', in_array(config('session.same_site'), ['lax', 'strict'], true), (string) config('session.same_site'), 'SESSION_SAME_SITE harus lax atau strict.'),
            $this->result('Scheduler', $this->schedule->events() !== [], count($this->schedule->events()).' tugas terdaftar.', 'Tidak ada tugas scheduler terdaftar.'),
            $this->binaryCheck('pg_dump', (string) config('operations.database_backup.pg_dump_binary')),
            $this->binaryCheck('psql', (string) config('operations.database_backup.psql_binary')),
            $this->offsiteBackupCheck(),
            $this->warning('Optimasi Laravel', app()->configurationIsCached() ? 'Konfigurasi sudah di-cache.' : 'Jalankan php artisan optimize pada proses deployment.'),
        ];

        $failures = collect($checks)->where('status', 'fail')->count();
        $warnings = collect($checks)->where('status', 'warning')->count();

        return ['ready' => $failures === 0, 'checks' => $checks, 'failures' => $failures, 'warnings' => $warnings];
    }

    private function hasValidApplicationKey(): bool
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = is_string($decoded) ? $decoded : '';
        }

        return Encrypter::supported($key, (string) config('app.cipher'));
    }

    /** @return list<array{name: string, status: string, message: string}> */
    private function healthChecks(): array
    {
        $health = $this->health->check();

        return [
            $this->result('Koneksi database', $health['checks']['database']['healthy'], $health['checks']['database']['message'], $health['checks']['database']['message']),
            $this->result('Storage privat', $health['checks']['storage']['healthy'], $health['checks']['storage']['message'], $health['checks']['storage']['message']),
        ];
    }

    /** @return array{name: string, status: string, message: string} */
    private function migrationCheck(): array
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return $this->failure('Migrasi database', 'Tabel migrasi belum tersedia.');
            }

            $files = $this->migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $this->migrator->getRepository()->getRan());

            return $this->result('Migrasi database', $pending === [], 'Semua migrasi sudah dijalankan.', count($pending).' migrasi belum dijalankan.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('Migrasi database', 'Status migrasi tidak dapat diperiksa.');
        }
    }

    /** @return list<array{name: string, status: string, message: string}> */
    private function runtimeDriverChecks(): array
    {
        $queue = (string) config('queue.default');
        $cache = (string) config('cache.default');
        $session = (string) config('session.driver');
        $checks = [
            $this->result('Queue', ! in_array($queue, ['sync', 'null'], true), $queue, 'QUEUE_CONNECTION tidak boleh sync atau null.'),
            $this->result('Cache', ! in_array($cache, ['array', 'null'], true), $cache, 'CACHE_STORE tidak boleh array atau null.'),
            $this->result('Session', ! in_array($session, ['array', 'cookie'], true), $session, 'SESSION_DRIVER tidak boleh array atau cookie.'),
            $this->result('Log level', $this->logLevel() !== 'debug', $this->logLevel(), 'LOG_LEVEL tidak boleh debug.'),
        ];

        $requiredTables = $queue === 'database' ? ['jobs', 'failed_jobs'] : [];
        if ($cache === 'database') {
            array_push($requiredTables, 'cache', 'cache_locks');
        }
        if ($session === 'database') {
            $requiredTables[] = 'sessions';
        }

        $checks[] = $this->runtimeTablesCheck($requiredTables);

        return $checks;
    }

    /**
     * @param  list<string>  $requiredTables
     * @return array{name: string, status: string, message: string}
     */
    private function runtimeTablesCheck(array $requiredTables): array
    {
        try {
            $missingTables = collect($requiredTables)->unique()->reject(fn (string $table): bool => Schema::hasTable($table))->values()->all();

            return $this->result('Tabel runtime', $missingTables === [], 'Tabel queue/cache/session tersedia.', 'Tabel belum tersedia: '.implode(', ', $missingTables));
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('Tabel runtime', 'Tabel queue/cache/session tidak dapat diperiksa.');
        }
    }

    private function logLevel(): string
    {
        return (string) config(
            'logging.channels.'.config('logging.default').'.level',
            config('logging.channels.single.level'),
        );
    }

    /** @return array{name: string, status: string, message: string} */
    private function binaryCheck(string $name, string $binary): array
    {
        $path = $this->executableFinder->find($binary);

        return $this->result("Binary {$name}", $path !== null, $path ?? $binary, "{$binary} tidak ditemukan di server.");
    }

    /** @return array{name: string, status: string, message: string} */
    private function offsiteBackupCheck(): array
    {
        $disk = config('operations.database_backup.offsite_disk');

        if (! is_string($disk) || $disk === '' || $disk === 'local') {
            return $this->failure('Penyimpanan backup offsite', 'DB_BACKUP_OFFSITE_DISK harus menunjuk ke disk eksternal yang terpisah dari storage lokal.');
        }

        $path = '.readiness-'.Str::uuid();

        try {
            $storage = Storage::disk($disk);
            $written = $storage->put($path, 'ok');
            $readable = $written && $storage->get($path) === 'ok';
            $storage->delete($path);

            return $this->result('Penyimpanan backup offsite', $readable, "Disk {$disk} dapat dibaca dan ditulis.", "Disk {$disk} tidak dapat dibaca atau ditulis.");
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure('Penyimpanan backup offsite', "Disk {$disk} tidak dapat diakses.");
        }
    }

    /** @return array{name: string, status: string, message: string} */
    private function result(string $name, bool $passes, string $success, string $failure): array
    {
        return ['name' => $name, 'status' => $passes ? 'pass' : 'fail', 'message' => $passes ? $success : $failure];
    }

    /** @return array{name: string, status: string, message: string} */
    private function failure(string $name, string $message): array
    {
        return ['name' => $name, 'status' => 'fail', 'message' => $message];
    }

    /** @return array{name: string, status: string, message: string} */
    private function warning(string $name, string $message): array
    {
        return ['name' => $name, 'status' => 'warning', 'message' => $message];
    }
}
