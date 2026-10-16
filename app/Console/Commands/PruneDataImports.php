<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('system:prune-data-imports {--hours=24 : Hapus file impor yang lebih lama dari jumlah jam ini}')]
#[Description('Menghapus file impor sementara yang sudah kedaluwarsa')]
class PruneDataImports extends Command
{
    public function handle(): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);

        if ($hours === false || $hours < 1) {
            $this->error('Opsi --hours harus berupa bilangan bulat minimal 1.');

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->timestamp;
        $expiredFiles = collect($disk->allFiles('data-imports'))
            ->filter(fn (string $path): bool => $disk->lastModified($path) <= $cutoff)
            ->values()
            ->all();

        if ($expiredFiles !== []) {
            $disk->delete($expiredFiles);
        }

        $this->info(count($expiredFiles).' file impor kedaluwarsa berhasil dihapus.');

        return self::SUCCESS;
    }
}
