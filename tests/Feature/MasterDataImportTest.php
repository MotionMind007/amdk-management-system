<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OperationalIntegrityService;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_system_administrators_can_access_data_imports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($admin)->get(route('admin.data-imports.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.data-imports.index'))->assertOk();
    }

    public function test_owner_can_download_an_excel_compatible_csv_template(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($owner)->get(route('admin.data-imports.template', 'products'));

        $response->assertOk();
        $response->assertDownload('template-products.csv');
        $this->assertStringContainsString('sku;nama;jenis;kode_satuan', $response->streamedContent());
    }

    public function test_product_preview_does_not_persist_until_confirmed(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Unit::factory()->create(['code' => 'KRT', 'name' => 'Karton', 'allows_decimal' => false]);
        $file = UploadedFile::fake()->createWithContent(
            'produk.csv',
            "sku;nama;jenis;kode_satuan;stok_minimum;stok_maksimum;aktif;catatan\nRH-330ML;Robong Holo 330ml;produk_jadi;KRT;10;500;ya;Produk karton\n",
        );

        $preview = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'products',
            'file' => $file,
        ]);

        $preview->assertOk()->assertSee('1 baris valid')->assertSee('Konfirmasi Impor');
        $this->assertDatabaseCount('products', 0);

        $token = $preview->viewData('token');
        $confirm = $this->post(route('admin.data-imports.confirm', $token));

        $confirm->assertRedirect(route('admin.data-imports.index'));
        $this->assertDatabaseHas('products', [
            'sku' => 'RH-330ML',
            'name' => 'Robong Holo 330ml',
            'minimum_stock' => 10,
            'maximum_stock' => 500,
            'is_active' => true,
        ]);
        $this->assertSame('Mengimpor 1 data Master Produk dari file produk.csv.', AuditLog::query()->sole()->description);
    }

    public function test_invalid_and_duplicate_rows_cannot_be_imported(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        Unit::factory()->create(['code' => 'KRT', 'name' => 'Karton', 'allows_decimal' => false]);
        Product::factory()->create(['sku' => 'EXISTING']);
        $file = UploadedFile::fake()->createWithContent(
            'produk.csv',
            "sku;nama;jenis;kode_satuan;stok_minimum;stok_maksimum;aktif;catatan\nEXISTING;Produk Lama;produk_jadi;KRT;0;;ya;\nNEW-01;Produk Baru;jenis_salah;KRT;0;;ya;\nNEW-01;Produk Duplikat;produk_jadi;KRT;0;;ya;\n",
        );

        $response = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'products',
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertSee('3 baris perlu diperbaiki')
            ->assertSee('Kode duplikat dengan baris 3.')
            ->assertDontSee('Konfirmasi Impor');
        $this->assertDatabaseCount('products', 1);
    }

    /** @param array<string, mixed> $expected */
    #[DataProvider('otherMasterDataProvider')]
    public function test_customer_supplier_and_warehouse_files_can_be_previewed_and_imported(
        string $type,
        string $filename,
        string $content,
        string $table,
        array $expected,
    ): void {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $file = UploadedFile::fake()->createWithContent($filename, $content);

        $preview = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => $type,
            'file' => $file,
        ]);

        $preview->assertOk()->assertSee('1 baris valid');
        $this->post(route('admin.data-imports.confirm', $preview->viewData('token')))
            ->assertRedirect(route('admin.data-imports.index'));
        $this->assertDatabaseHas($table, $expected);
    }

    /** @return array<string, array{string, string, string, string, array<string, mixed>}> */
    public static function otherMasterDataProvider(): array
    {
        return [
            'customer' => [
                'customers',
                'pelanggan.csv',
                "kode_pelanggan;nama;telepon;email;alamat;batas_kredit;termin_hari;aktif;catatan\nPLG-01;Toko Maju;0812;toko@example.com;Jayapura;5000000;30;ya;Pelanggan lama\n",
                'customers',
                ['customer_code' => 'PLG-01', 'name' => 'Toko Maju', 'status' => 'active', 'payment_term' => 30],
            ],
            'supplier' => [
                'suppliers',
                'supplier.csv',
                "kode_supplier;nama;telepon;email;alamat;termin_hari;aktif;catatan\nSUP-01;PT Bahan;0813;supplier@example.com;Jayapura;14;ya;Supplier lama\n",
                'suppliers',
                ['supplier_code' => 'SUP-01', 'name' => 'PT Bahan', 'status' => 'active', 'payment_term' => 14],
            ],
            'warehouse' => [
                'warehouses',
                'gudang.csv',
                "kode_gudang;nama;alamat;aktif\nGDG-02;Gudang Kedua;Jayapura;ya\n",
                'warehouses',
                ['code' => 'GDG-02', 'name' => 'Gudang Kedua', 'is_active' => true],
            ],
        ];
    }

    public function test_another_user_cannot_confirm_an_owned_preview(): void
    {
        $this->fakeLocalStorage();
        $firstOwner = User::factory()->create(['role' => UserRole::Owner]);
        $secondOwner = User::factory()->create(['role' => UserRole::Owner]);
        $file = UploadedFile::fake()->createWithContent(
            'gudang.csv',
            "kode_gudang;nama;alamat;aktif\nGDG-02;Gudang Kedua;Jayapura;ya\n",
        );

        $preview = $this->actingAs($firstOwner)->post(route('admin.data-imports.preview'), [
            'type' => 'warehouses',
            'file' => $file,
        ]);

        $token = $preview->viewData('token');
        $this->actingAs($secondOwner)->post(route('admin.data-imports.confirm', $token))->assertNotFound();
        $this->assertDatabaseMissing('warehouses', ['code' => 'GDG-02']);
    }

    public function test_employee_import_creates_employee_and_login_without_exposing_password_in_preview(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $file = UploadedFile::fake()->createWithContent(
            'karyawan.csv',
            "kode_karyawan;nama;telepon;email_login;alamat;jabatan;departemen;tanggal_masuk;role;password_awal;aktif\nKRY-01;Operator Gudang;0812;operator@example.com;Jayapura;Operator;Gudang;;warehouse;PasswordAwal123!;ya\n",
        );

        $preview = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'employees',
            'file' => $file,
        ]);

        $preview->assertOk()
            ->assertSee('1 baris valid')
            ->assertSee('••••••••')
            ->assertDontSee('PasswordAwal123!');

        $this->post(route('admin.data-imports.confirm', $preview->viewData('token')))
            ->assertRedirect(route('admin.data-imports.index'));

        $user = User::query()->where('email', 'operator@example.com')->firstOrFail();
        $this->assertSame(UserRole::Warehouse, $user->role);
        $this->assertTrue(Hash::check('PasswordAwal123!', $user->password));
        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'employee_code' => 'KRY-01',
            'name' => 'Operator Gudang',
            'join_date' => null,
        ]);
    }

    public function test_opening_stock_import_creates_reviewable_drafts_grouped_by_date_and_warehouse(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $warehouse = Warehouse::factory()->create(['code' => 'GDG-01', 'is_active' => true]);
        $firstProduct = Product::factory()->create(['sku' => 'BAHAN-01', 'is_active' => true]);
        $secondProduct = Product::factory()->create(['sku' => 'BAHAN-02', 'is_active' => true]);
        $date = today()->toDateString();
        $file = UploadedFile::fake()->createWithContent(
            'stok-awal.csv',
            "tanggal_stok;kode_gudang;sku;jumlah;catatan\n{$date};{$warehouse->code};{$firstProduct->sku};100;Hitung fisik\n{$date};{$warehouse->code};{$secondProduct->sku};50;Hitung fisik\n",
        );

        $preview = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'opening_stocks',
            'file' => $file,
        ]);

        $preview->assertOk()->assertSee('2 baris valid');
        $this->post(route('admin.data-imports.confirm', $preview->viewData('token')))
            ->assertRedirect(route('admin.data-imports.index'));

        $openingStock = OpeningStock::query()->sole();
        $this->assertSame('draft', $openingStock->status);
        $this->assertSame($warehouse->id, $openingStock->warehouse_id);
        $this->assertSame(2, $openingStock->items()->count());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stock_balances', 0);

        $this->post(route('admin.opening-stocks.post', $openingStock))
            ->assertSessionHas('success');

        $this->assertSame('posted', $openingStock->fresh()->status);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $firstProduct->id,
            'quantity' => 100,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $secondProduct->id,
            'quantity' => 50,
        ]);
    }

    public function test_opening_stock_preview_rejects_product_that_already_has_a_movement(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $warehouse = Warehouse::factory()->create(['code' => 'GDG-01', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'BAHAN-01', 'is_active' => true]);
        StockMovement::factory()->create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id]);
        $date = today()->toDateString();
        $file = UploadedFile::fake()->createWithContent(
            'stok-awal.csv',
            "tanggal_stok;kode_gudang;sku;jumlah;catatan\n{$date};{$warehouse->code};{$product->sku};100;Hitung fisik\n",
        );

        $response = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'opening_stocks',
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertSee('1 baris perlu diperbaiki')
            ->assertSee('Gunakan Penyesuaian Stok')
            ->assertDontSee('Konfirmasi Impor');
    }

    public function test_cash_balance_import_creates_an_account_and_matching_opening_ledger(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $date = today()->toDateString();
        $file = UploadedFile::fake()->createWithContent(
            'saldo-kas.csv',
            "kode_akun;nama_akun;jenis;tanggal_saldo;saldo_awal;aktif\nBANK-BRI;Bank BRI;bank;{$date};10000000;ya\n",
        );

        $preview = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'cash_balances',
            'file' => $file,
        ]);

        $preview->assertOk()->assertSee('1 baris valid');
        $this->post(route('admin.data-imports.confirm', $preview->viewData('token')))
            ->assertRedirect(route('admin.data-imports.index'));

        $account = CashAccount::query()->where('code', 'BANK-BRI')->sole();
        $transaction = CashTransaction::query()->where('cash_account_id', $account->id)->sole();
        $this->assertSame('10000000.00', $account->balance);
        $this->assertSame('10000000.00', $transaction->amount);
        $this->assertSame('in', $transaction->direction);
        $this->assertStringStartsWith('CASH-OPEN-', $transaction->reference_number);
        $this->assertTrue($this->app->make(OperationalIntegrityService::class)->check()['healthy']);
    }

    public function test_opening_balances_cannot_be_imported_into_a_closed_period(): void
    {
        $this->fakeLocalStorage();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        AccountingPeriod::factory()->create(['period' => '2026-01', 'status' => 'closed']);
        $file = UploadedFile::fake()->createWithContent(
            'saldo-kas.csv',
            "kode_akun;nama_akun;jenis;tanggal_saldo;saldo_awal;aktif\nBANK-BRI;Bank BRI;bank;2026-01-31;10000000;ya\n",
        );

        $response = $this->actingAs($owner)->post(route('admin.data-imports.preview'), [
            'type' => 'cash_balances',
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertSee('Periode 2026-01 sudah ditutup')
            ->assertDontSee('Konfirmasi Impor');
        $this->assertDatabaseMissing('cash_accounts', ['code' => 'BANK-BRI']);
    }
}
