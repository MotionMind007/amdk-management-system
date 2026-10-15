<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupVerifier
{
    public function __construct(private DatabaseBackupService $backups) {}

    /** @return array{filename: string, driver: string, checksum: string, tables: int, rows: int|null, verified_at: int} */
    public function verify(?string $filename = null): array
    {
        $filename ??= $this->latestFilename();
        $absolutePath = $this->absolutePath($filename);
        $driver = DB::connection()->getDriverName();

        $result = match ($driver) {
            'sqlite' => $this->verifySqlite($absolutePath),
            'pgsql' => $this->verifyPostgres($absolutePath),
            default => throw new RuntimeException("Verifikasi restore belum mendukung driver {$driver}."),
        };

        $verification = [
            'filename' => $filename,
            'driver' => $driver,
            'checksum' => hash_file('sha256', $absolutePath),
            'tables' => $result['tables'],
            'rows' => $result['rows'],
            'verified_at' => now()->timestamp,
        ];

        Storage::disk('local')->put(
            'backups/'.$filename.'.verified.json',
            json_encode($verification, JSON_THROW_ON_ERROR),
        );
        $this->backups->replicateVerified($filename);

        return $verification;
    }

    private function latestFilename(): string
    {
        $filename = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path): bool => preg_match('/\Adatabase-[a-zA-Z0-9._-]+\.sql\z/', basename($path)) === 1)
            ->sortByDesc(fn (string $path): int => Storage::disk('local')->lastModified($path))
            ->map(fn (string $path): string => basename($path))
            ->first();

        if ($filename === null) {
            throw new RuntimeException('Belum ada file backup database yang dapat diverifikasi.');
        }

        return $filename;
    }

    private function absolutePath(string $filename): string
    {
        if (basename($filename) !== $filename || preg_match('/\Adatabase-[a-zA-Z0-9._-]+\.sql\z/', $filename) !== 1) {
            throw new RuntimeException('Nama file backup tidak valid.');
        }

        $relativePath = 'backups/'.$filename;
        if (! Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException("File backup {$filename} tidak ditemukan.");
        }

        return Storage::disk('local')->path($relativePath);
    }

    /** @return array{tables: int, rows: int} */
    private function verifySqlite(string $absolutePath): array
    {
        $temporaryPath = dirname($absolutePath).DIRECTORY_SEPARATOR.'.verify-'.Str::uuid().'.sqlite';

        try {
            $pdo = new PDO('sqlite:'.$temporaryPath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('PRAGMA journal_mode=MEMORY');
            $sql = file_get_contents($absolutePath);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('File backup kosong atau tidak dapat dibaca.');
            }

            $pdo->exec($sql);
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok') {
                throw new RuntimeException('Database hasil restore gagal melewati pemeriksaan integritas SQLite.');
            }

            if ($pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) {
                throw new RuntimeException('Database hasil restore memiliki pelanggaran foreign key.');
            }

            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            if ($tables === []) {
                throw new RuntimeException('Database hasil restore tidak memiliki tabel aplikasi.');
            }

            $rows = collect($tables)->sum(function (string $table) use ($pdo): int {
                $quotedTable = '"'.str_replace('"', '""', $table).'"';

                return (int) $pdo->query("SELECT COUNT(*) FROM {$quotedTable}")->fetchColumn();
            });

            return ['tables' => count($tables), 'rows' => $rows];
        } finally {
            $pdo = null;

            foreach ([$temporaryPath, $temporaryPath.'-journal', $temporaryPath.'-wal', $temporaryPath.'-shm'] as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    @unlink($temporaryFile);
                }
            }
        }
    }

    /** @return array{tables: int, rows: null} */
    private function verifyPostgres(string $absolutePath): array
    {
        $connection = DB::connection()->getConfig();
        $database = 'backup_verify_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6));
        $created = false;

        try {
            $maintenanceDatabase = (string) $connection['database'];
            $this->runPsql($connection, $maintenanceDatabase, ['--command=CREATE DATABASE "'.$database.'"']);
            $created = true;
            $this->runPsql($connection, $database, ['--single-transaction', '--file='.$absolutePath]);
            $process = $this->runPsql($connection, $database, [
                '--tuples-only',
                '--no-align',
                "--command=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'",
            ]);
            $tables = (int) trim($process->getOutput());

            if ($tables === 0) {
                throw new RuntimeException('Database hasil restore tidak memiliki tabel aplikasi.');
            }

            return ['tables' => $tables, 'rows' => null];
        } finally {
            if ($created) {
                $this->runPsql($connection, $maintenanceDatabase, ['--command=DROP DATABASE IF EXISTS "'.$database.'" WITH (FORCE)']);
            }
        }
    }

    /** @param array<string, mixed> $connection */
    private function runPsql(array $connection, string $database, array $arguments): Process
    {
        $process = new Process([
            (string) config('operations.database_backup.psql_binary'),
            '--set=ON_ERROR_STOP=1',
            '--host='.(string) $connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'],
            '--dbname='.$database,
            ...$arguments,
        ], null, [
            'PGPASSWORD' => (string) $connection['password'],
            'PGSSLMODE' => (string) ($connection['sslmode'] ?? 'prefer'),
        ]);
        $process->setTimeout(120)->mustRun();

        return $process;
    }
}
