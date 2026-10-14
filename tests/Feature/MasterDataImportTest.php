<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
