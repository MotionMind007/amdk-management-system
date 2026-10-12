<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    /** @return array{filename: string, size: int, created_at: int} */
    public function create(): array
    {
        $driver = DB::connection()->getDriverName();
        $filename = 'database-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.sql';
        $relativePath = 'backups/'.$filename;
        Storage::disk('local')->makeDirectory('backups');
        $absolutePath = Storage::disk('local')->path($relativePath);

        match ($driver) {
            'sqlite' => $this->backupSqlite($absolutePath),
            'pgsql' => $this->backupPostgres($absolutePath),
            default => throw new RuntimeException("Backup otomatis belum mendukung driver {$driver}."),
        };

        clearstatcache(true, $absolutePath);

        return ['filename' => $filename, 'size' => Storage::disk('local')->size($relativePath), 'created_at' => Storage::disk('local')->lastModified($relativePath)];
    }

    /** @return list<array{filename: string, size: int, created_at: int, verified_at: int|null}> */
    public function all(): array
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path): bool => preg_match('/\Adatabase-[a-zA-Z0-9._-]+\.sql\z/', basename($path)) === 1)
            ->map(function (string $path): array {
                $verificationPath = $path.'.verified.json';
                $verification = Storage::disk('local')->exists($verificationPath)
                    ? json_decode(Storage::disk('local')->get($verificationPath), true)
                    : null;
                $checksum = hash_file('sha256', Storage::disk('local')->path($path));
                $verifiedAt = is_array($verification)
                    && is_string($verification['checksum'] ?? null)
                    && is_string($checksum)
                    && hash_equals($checksum, $verification['checksum'])
                        ? (int) ($verification['verified_at'] ?? 0)
                        : null;

                return [
                    'filename' => basename($path),
                    'size' => Storage::disk('local')->size($path),
                    'created_at' => Storage::disk('local')->lastModified($path),
                    'verified_at' => $verifiedAt ?: null,
                ];
            })
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    private function backupSqlite(string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('File backup SQLite tidak dapat dibuat.');
        }

        fwrite($handle, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n");
        $schemas = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $pdo = DB::connection()->getPdo();

        foreach ($schemas as $schema) {
            fwrite($handle, $schema->sql.";\n");
            foreach (DB::table($schema->name)->cursor() as $row) {
                $values = collect(get_object_vars($row))->map(function (mixed $value) use ($pdo): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return is_int($value) || is_float($value) ? (string) $value : $pdo->quote((string) $value);
                })->implode(', ');
                $table = '"'.str_replace('"', '""', $schema->name).'"';
                fwrite($handle, "INSERT INTO {$table} VALUES ({$values});\n");
            }
        }

        $schemaObjects = DB::select("SELECT sql FROM sqlite_master WHERE type IN ('index', 'trigger', 'view') AND sql IS NOT NULL ORDER BY type, name");
        foreach ($schemaObjects as $schemaObject) {
            fwrite($handle, $schemaObject->sql.";\n");
        }

        fwrite($handle, "COMMIT;\nPRAGMA foreign_keys=ON;\n");
        fclose($handle);
    }

    private function backupPostgres(string $absolutePath): void
    {
        $connection = DB::connection()->getConfig();
        $process = new Process([
            (string) config('operations.database_backup.pg_dump_binary'), '--format=plain', '--no-owner', '--no-privileges',
            '--host='.(string) $connection['host'], '--port='.(string) $connection['port'],
            '--username='.(string) $connection['username'], '--dbname='.(string) $connection['database'],
            '--file='.$absolutePath,
        ], null, ['PGPASSWORD' => (string) $connection['password']]);
        $process->setTimeout(120)->mustRun();
    }
}
