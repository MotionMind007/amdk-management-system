<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneDataImportsTest extends TestCase
{
    public function test_command_deletes_only_expired_import_files(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $expiredPath = 'data-imports/1/expired.csv';
        $recentPath = 'data-imports/1/recent.csv';
        $disk->put($expiredPath, 'old');
        $disk->put($recentPath, 'new');
        touch($disk->path($expiredPath), now()->subHours(25)->timestamp);

        $this->artisan('system:prune-data-imports', ['--hours' => 24])
            ->expectsOutput('1 file impor kedaluwarsa berhasil dihapus.')
            ->assertSuccessful();

        $disk->assertMissing($expiredPath);
        $disk->assertExists($recentPath);
    }

    public function test_command_rejects_an_invalid_retention_period(): void
    {
        $this->artisan('system:prune-data-imports', ['--hours' => 0])
            ->expectsOutput('Opsi --hours harus berupa bilangan bulat minimal 1.')
            ->assertFailed();
    }
}
