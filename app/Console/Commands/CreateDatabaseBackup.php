<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Services\DatabaseBackupVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:backup-database {--verify : Uji restore setelah backup dibuat} {--retention-days=30 : Hapus backup yang lebih lama dari jumlah hari ini}')]
#[Description('Membuat backup database dan menyalinnya ke storage offsite yang dikonfigurasi')]
class CreateDatabaseBackup extends Command
{
    public function handle(DatabaseBackupService $backups, DatabaseBackupVerifier $verifier): int
    {
        $retentionDays = (int) $this->option('retention-days');
        if ($retentionDays < 1) {
            $this->error('Retensi backup minimal satu hari.');

            return self::FAILURE;
        }

        $backup = $backups->create();
        $this->info("Backup {$backup['filename']} berhasil dibuat.");

        if ($backup['offsite_disk'] !== null) {
            $this->info("Backup berhasil disalin ke disk offsite {$backup['offsite_disk']}.");
        } else {
            $this->warn('Disk offsite belum dikonfigurasi; backup hanya tersedia di storage lokal.');
        }

        if ($this->option('verify')) {
            $verification = $verifier->verify($backup['filename']);
            $this->info("Restore backup terverifikasi: {$verification['tables']} tabel dan {$verification['integrity_checks']} pemeriksaan integritas operasional lulus.");
        }

        $pruned = $backups->pruneOlderThan($retentionDays);
        $this->info("Retensi {$retentionDays} hari diterapkan; {$pruned} backup lama dihapus.");

        return self::SUCCESS;
    }
}
