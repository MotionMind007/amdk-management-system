<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Customer;
use App\Models\OpeningStock;
use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\ProductType;
use App\Services\OpeningStockService;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UatSeeder extends Seeder
{
    public function run(OpeningStockService $openingStockService): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('UatSeeder tidak boleh dijalankan di lingkungan production.');
        }

        $password = config('operations.uat.password');

        if (! is_string($password) || mb_strlen($password) < 8) {
            throw new RuntimeException('Atur UAT_PASSWORD minimal 8 karakter sebelum menjalankan UatSeeder.');
        }

        DB::transaction(function () use ($openingStockService, $password): void {
            $users = $this->seedUsers($password);
            $units = $this->seedUnits();
            $warehouse = Warehouse::query()->updateOrCreate(
                ['code' => 'UAT-GDG'],
                ['name' => 'Gudang UAT', 'address' => 'Jayapura', 'is_active' => true],
            );

            $customer = Customer::withTrashed()->updateOrCreate(
                ['customer_code' => 'UAT-PLG-001'],
                [
                    'name' => 'Toko Pelanggan UAT',
                    'phone' => '081200000001',
                    'email' => 'pelanggan.uat@example.com',
                    'address' => 'Jayapura',
                    'credit_limit' => 50000000,
                    'payment_term' => 30,
                    'status' => 'active',
                    'notes' => 'Data dummy untuk UAT.',
                ],
            );
            $customer->restore();
            $supplier = Supplier::withTrashed()->updateOrCreate(
                ['supplier_code' => 'UAT-SUP-001'],
                [
                    'name' => 'Supplier Bahan UAT',
                    'phone' => '081200000002',
                    'email' => 'supplier.uat@example.com',
                    'address' => 'Jayapura',
                    'payment_term' => 30,
                    'status' => 'active',
                    'notes' => 'Data dummy untuk UAT.',
                ],
            );
            $supplier->restore();

            $products = $this->seedProducts($units);
            $this->seedCompositions($products);
            $this->seedOpeningStock($products, $warehouse, $users['super_admin'], $openingStockService);
            $this->seedCashBalances($users['super_admin']);
        });

        $this->command?->info('Data UAT siap. Gunakan akun uat.*@amdk.local dengan password dari UAT_PASSWORD.');
    }

    /** @return array<string, User> */
    private function seedUsers(string $password): array
    {
        $definitions = [
            'owner' => ['name' => 'Owner UAT', 'email' => 'uat.owner@amdk.local', 'role' => UserRole::Owner],
            'super_admin' => ['name' => 'Super Admin UAT', 'email' => 'uat.superadmin@amdk.local', 'role' => UserRole::SuperAdministrator],
            'admin' => ['name' => 'Admin UAT', 'email' => 'uat.admin@amdk.local', 'role' => UserRole::Admin],
            'warehouse' => ['name' => 'Gudang UAT', 'email' => 'uat.gudang@amdk.local', 'role' => UserRole::Warehouse],
            'sales' => ['name' => 'Sales UAT', 'email' => 'uat.sales@amdk.local', 'role' => UserRole::Sales],
        ];
        $users = [];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => $password,
                    'role' => $definition['role'],
                    'is_active' => true,
                ],
            );
            $user->forceFill(['email_verified_at' => now()])->save();
            $users[$key] = $user;
        }

        return $users;
    }

    /** @return array<string, Unit> */
    private function seedUnits(): array
    {
        return collect([
            'PCS' => ['name' => 'Pcs', 'allows_decimal' => false],
            'KRT' => ['name' => 'Karton', 'allows_decimal' => false],
        ])->mapWithKeys(function (array $attributes, string $code): array {
            $unit = Unit::query()->updateOrCreate(['code' => $code], $attributes);

            return [$code => $unit];
        })->all();
    }

    /**
     * @param  array<string, Unit>  $units
     * @return array<string, Product>
     */
    private function seedProducts(array $units): array
    {
        $definitions = [
            'preform_330' => ['sku' => 'UAT-PREF-330', 'name' => 'Preform 330ml UAT', 'unit' => 'PCS', 'type' => ProductType::RawMaterial],
            'preform_600' => ['sku' => 'UAT-PREF-600', 'name' => 'Preform 600ml UAT', 'unit' => 'PCS', 'type' => ProductType::RawMaterial],
            'bottle_330' => ['sku' => 'UAT-BTL-330', 'name' => 'Botol 330ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'bottle_600' => ['sku' => 'UAT-BTL-600', 'name' => 'Botol 600ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'cap' => ['sku' => 'UAT-TUTUP', 'name' => 'Tutup Botol UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'label_330' => ['sku' => 'UAT-LBL-330', 'name' => 'Label 330ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'label_600' => ['sku' => 'UAT-LBL-600', 'name' => 'Label 600ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'carton_330' => ['sku' => 'UAT-KRT-330', 'name' => 'Karton 330ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'carton_600' => ['sku' => 'UAT-KRT-600', 'name' => 'Karton 600ml UAT', 'unit' => 'PCS', 'type' => ProductType::PackagingMaterial],
            'finished_330' => ['sku' => 'UAT-RH-330', 'name' => 'Robong Holo 330ml UAT', 'unit' => 'KRT', 'type' => ProductType::FinishedGood],
            'finished_600' => ['sku' => 'UAT-RH-600', 'name' => 'Robong Holo 600ml UAT', 'unit' => 'KRT', 'type' => ProductType::FinishedGood],
        ];
        $products = [];

        foreach ($definitions as $key => $definition) {
            $product = Product::withTrashed()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'unit_id' => $units[$definition['unit']]->id,
                    'type' => $definition['type'],
                    'minimum_stock' => 10,
                    'maximum_stock' => null,
                    'is_active' => true,
                    'notes' => 'Data dummy untuk UAT.',
                ],
            );
            $product->restore();
            $products[$key] = $product;
        }

        return $products;
    }

    /** @param array<string, Product> $products */
    private function seedCompositions(array $products): void
    {
        $components = [
            'finished_330' => ['bottle_330' => 24, 'cap' => 24, 'label_330' => 24, 'carton_330' => 1],
            'finished_600' => ['bottle_600' => 24, 'cap' => 24, 'label_600' => 24, 'carton_600' => 1],
        ];

        foreach ($components as $finishedKey => $materials) {
            foreach ($materials as $materialKey => $quantity) {
                ProductComposition::query()->updateOrCreate(
                    ['product_id' => $products[$finishedKey]->id, 'component_product_id' => $products[$materialKey]->id],
                    ['quantity' => $quantity],
                );
            }
        }
    }

    /** @param array<string, Product> $products */
    private function seedOpeningStock(array $products, Warehouse $warehouse, User $user, OpeningStockService $openingStockService): void
    {
        $openingStock = OpeningStock::query()->firstOrCreate(
            ['number' => 'UAT-STOK-AWAL-001'],
            [
                'stock_date' => today(),
                'warehouse_id' => $warehouse->id,
                'status' => 'draft',
                'notes' => 'Saldo awal dummy untuk UAT.',
                'created_by' => $user->id,
            ],
        );

        if (! $openingStock->wasRecentlyCreated) {
            return;
        }

        $quantities = [
            'preform_330' => 5000,
            'preform_600' => 5000,
            'bottle_330' => 240,
            'bottle_600' => 240,
            'cap' => 10000,
            'label_330' => 5000,
            'label_600' => 5000,
            'carton_330' => 500,
            'carton_600' => 500,
            'finished_330' => 50,
            'finished_600' => 50,
        ];

        foreach ($quantities as $key => $quantity) {
            $openingStock->items()->create(['product_id' => $products[$key]->id, 'quantity' => $quantity]);
        }

        $openingStockService->post($openingStock, $user);
    }

    private function seedCashBalances(User $user): void
    {
        $accounts = [
            ['code' => 'UAT-KAS', 'name' => 'Kas UAT', 'type' => 'cash', 'amount' => 25000000],
            ['code' => 'UAT-BANK', 'name' => 'Bank UAT', 'type' => 'bank', 'amount' => 50000000],
        ];

        foreach ($accounts as $definition) {
            $account = CashAccount::query()->firstOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'type' => $definition['type'], 'balance' => 0, 'is_active' => true],
            );
            $reference = 'UAT-SALDO-AWAL-'.$definition['code'];

            if (CashTransaction::query()->where('reference_number', $reference)->exists()) {
                continue;
            }

            CashTransaction::query()->create([
                'cash_account_id' => $account->id,
                'transaction_date' => today(),
                'direction' => 'in',
                'amount' => $definition['amount'],
                'reference_number' => $reference,
                'description' => 'Saldo awal dummy untuk UAT.',
                'user_id' => $user->id,
            ]);
            $account->increment('balance', $definition['amount']);
        }
    }
}
