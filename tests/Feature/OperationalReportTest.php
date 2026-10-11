<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_filter_and_export_sales_report(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create(['name' => 'Toko Sentosa']);
        $warehouse = Warehouse::factory()->create();
        Sale::query()->create(['number' => 'INV-REPORT-001', 'customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'sale_date' => '2026-10-05', 'status' => 'unpaid', 'total' => 125000, 'outstanding_amount' => 125000, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('reports.operational.show', ['type' => 'sales', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'q' => 'Sentosa']))->assertOk()->assertSee('INV-REPORT-001')->assertSee('Toko Sentosa');
        $response = $this->actingAs($admin)->get(route('reports.operational.export', ['type' => 'sales', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']));
        $response->assertDownload('sales-2026-10-01-2026-10-31.csv');
        $this->assertStringContainsString('INV-REPORT-001', $response->streamedContent());
    }

    public function test_warehouse_role_cannot_access_company_reports(): void
    {
        $warehouseUser = User::factory()->create(['role' => UserRole::Warehouse]);

        $this->actingAs($warehouseUser)->get(route('reports.operational.show', 'sales'))->assertForbidden();
    }

    public function test_csv_export_escapes_spreadsheet_formulas_from_user_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $customer = Customer::factory()->create(['name' => '=HYPERLINK("https://example.test")']);
        Sale::query()->create(['number' => 'INV-SAFE', 'customer_id' => $customer->id, 'warehouse_id' => Warehouse::factory()->create()->id, 'sale_date' => today(), 'status' => 'unpaid', 'total' => 1000, 'outstanding_amount' => 1000, 'created_by' => $admin->id]);

        $content = $this->actingAs($admin)->get(route('reports.operational.export', 'sales'))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(';=HYPERLINK', $content);
    }

    #[DataProvider('reportTypes')]
    public function test_each_operational_report_renders_for_admin(string $type, string $title): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('reports.operational.show', $type))->assertOk()->assertSee($title);
    }

    /** @return array<string, array{string, string}> */
    public static function reportTypes(): array
    {
        return [
            'sales' => ['sales', 'Laporan Penjualan'],
            'purchases' => ['purchases', 'Laporan Penerimaan Pembelian'],
            'stock' => ['stock', 'Laporan Pergerakan Stok'],
            'production' => ['production', 'Laporan Produksi'],
            'cash' => ['cash', 'Laporan Kas dan Bank'],
            'receivables' => ['receivables', 'Laporan Piutang'],
            'payables' => ['payables', 'Laporan Hutang'],
        ];
    }
}
