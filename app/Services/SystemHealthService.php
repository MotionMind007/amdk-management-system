<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SystemHealthService
{
    /** @return array{healthy: bool, checks: array<string, array{healthy: bool, message: string}>} */
    public function check(): array
    {
        $checks = [
            'application' => ['healthy' => true, 'message' => 'Aplikasi dapat dijalankan.'],
            'database' => $this->database(),
            'storage' => $this->storage(),
        ];

        return ['healthy' => collect($checks)->every(fn (array $check): bool => $check['healthy']), 'checks' => $checks];
    }

    /** @return array{healthy: bool, message: string} */
    private function database(): array
    {
        try {
            DB::select('select 1');

            return ['healthy' => true, 'message' => 'Koneksi database normal.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['healthy' => false, 'message' => 'Database tidak dapat diakses.'];
        }
    }

    /** @return array{healthy: bool, message: string} */
    private function storage(): array
    {
        $path = '.health-check-'.Str::uuid();

        try {
            if (! Storage::disk('local')->put($path, 'ok')) {
                return ['healthy' => false, 'message' => 'Storage tidak dapat ditulis.'];
            }
            Storage::disk('local')->delete($path);

            return ['healthy' => true, 'message' => 'Storage dapat dibaca dan ditulis.'];
        } catch (Throwable $exception) {
            report($exception);

            return ['healthy' => false, 'message' => 'Storage tidak dapat diakses.'];
        }
    }
}
