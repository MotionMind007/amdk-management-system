<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('system:verify-backup {filename? : Nama file backup; kosongkan untuk memakai backup terbaru}')]
#[Description('Pulihkan backup ke database sementara dan periksa integritasnya tanpa menyentuh database aktif.')]
class VerifyDatabaseBackup extends Command
{
    public function handle(DatabaseBackupVerifier $verifier): int
    {
        try {
            $result = $verifier->verify($this->argument('filename'));
        } catch (Throwable $exception) {
            $this->error('Verifikasi backup gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['File', 'Driver', 'Checksum SHA-256', 'Tabel', 'Baris'], [[
            $result['filename'],
            $result['driver'],
            $result['checksum'],
            $result['tables'],
            $result['rows'] ?? 'Diverifikasi saat restore',
        ]]);
        $this->info('Backup berhasil dipulihkan dan diverifikasi di database sementara. Database aktif tidak diubah.');

        return self::SUCCESS;
    }
}
