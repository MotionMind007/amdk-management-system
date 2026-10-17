<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function fakeLocalStorage(): LocalFilesystemAdapter
    {
        $temporaryDisk = 'local-'.getmypid().'-'.Str::uuid();
        $fake = Storage::fake($temporaryDisk);
        $root = $fake->path('');

        Storage::set('local', $fake);
        $this->beforeApplicationDestroyed(
            static fn (): bool => (new Filesystem)->deleteDirectory($root),
        );

        return $fake;
    }
}
