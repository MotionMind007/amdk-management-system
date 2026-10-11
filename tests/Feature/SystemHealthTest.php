<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_health_route_checks_database_and_storage(): void
    {
        Storage::fake('local');

        $this->get('/up')->assertOk();
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_owner_can_view_detailed_system_health(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('admin.health'))->assertOk()->assertSee('Semua layanan normal')->assertSee('Koneksi database normal');
    }
}
