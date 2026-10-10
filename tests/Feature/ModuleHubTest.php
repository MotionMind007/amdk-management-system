<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModuleHubTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_sees_every_module(): void
    {
        $user = User::factory()->administrator()->create();

        $response = $this->actingAs($user)->get('/modules');

        $response->assertOk();
        $response->assertSee('Produksi');
        $response->assertSee('Penjualan');
        $response->assertSee('Karyawan');
        $response->assertSee('Sistem Admin');
    }

    public function test_sales_user_only_sees_authorized_modules(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/modules');

        $response->assertOk();
        $response->assertSee('Penjualan');
        $response->assertSee('Stok');
        $response->assertSee('Pelanggan');
        $response->assertDontSee('Sistem Admin');
        $response->assertDontSee('Karyawan');
        $response->assertDontSee('Pembelian');
    }

    public function test_sales_user_is_forbidden_from_system_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/modules/system')
            ->assertForbidden();
    }
}
