<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_owner_dashboard_counts_draft_purchase_orders_waiting_for_approval(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();
        PurchaseOrder::query()->create([
            'number' => 'PO-DASHBOARD-DRAFT',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
            'created_by' => $owner->id,
        ]);
        PurchaseOrder::query()->create([
            'number' => 'PO-DASHBOARD-REJECTED',
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'order_date' => today(),
            'status' => 'rejected',
            'created_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertViewHas('tasks', function (Collection $tasks): bool {
            $approvalTask = $tasks->firstWhere('label', 'PO menunggu persetujuan');

            return $approvalTask !== null && $approvalTask['count'] === 1;
        });
    }

    public function test_cancelled_sales_are_excluded_from_today_total_and_latest_sales(): void
    {
        $salesUser = User::factory()->create(['role' => UserRole::Sales]);
        $customer = Customer::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $postedSale = Sale::query()->create([
            'number' => 'INV-DASHBOARD-POSTED',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today(),
            'status' => 'posted',
            'total' => 100000,
            'created_by' => $salesUser->id,
        ]);
        Sale::query()->create([
            'number' => 'INV-DASHBOARD-CANCELLED',
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sale_date' => today(),
            'status' => 'cancelled',
            'total' => 900000,
            'created_by' => $salesUser->id,
        ]);

        $response = $this->actingAs($salesUser)->get(route('dashboard'));

        $response
            ->assertViewHas('stats', fn (array $stats): bool => collect($stats)->contains(
                fn (array $stat): bool => $stat['label'] === 'Penjualan hari ini' && $stat['value'] === 'Rp 100.000',
            ))
            ->assertViewHas('latestSales', fn (Collection $sales): bool => $sales->modelKeys() === [$postedSale->id]);
    }
}
