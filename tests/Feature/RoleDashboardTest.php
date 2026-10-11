<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_dashboard_only_shows_relevant_financial_metrics(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)->get(route('dashboard'))->assertOk()->assertSee('Penjualan hari ini')->assertSee('Piutang berjalan')->assertDontSee('Hutang berjalan');
    }

    public function test_warehouse_dashboard_hides_sales_and_finance_metrics(): void
    {
        $warehouse = User::factory()->create(['role' => UserRole::Warehouse]);

        $this->actingAs($warehouse)->get(route('dashboard'))->assertOk()->assertSee('Stok kritis')->assertDontSee('Penjualan hari ini')->assertDontSee('Hutang berjalan');
    }
}
