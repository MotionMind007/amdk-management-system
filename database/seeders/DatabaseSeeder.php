<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = config('operations.initial_admin.password');

        if (! is_string($adminPassword) || $adminPassword === '') {
            $this->command?->warn('ADMIN_PASSWORD belum diatur; user administrator tidak dibuat.');
        } else {
            if (mb_strlen($adminPassword) < 12) {
                throw new RuntimeException('Atur ADMIN_PASSWORD minimal 12 karakter sebelum menjalankan seeder awal.');
            }

            User::query()->updateOrCreate(
                ['email' => config('operations.initial_admin.email')],
                [
                    'name' => config('operations.initial_admin.name'),
                    'password' => $adminPassword,
                    'role' => UserRole::SuperAdministrator,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }

        Unit::query()->upsert([
            ['code' => 'KRT', 'name' => 'Karton', 'allows_decimal' => false],
            ['code' => 'PCS', 'name' => 'Pcs', 'allows_decimal' => false],
            ['code' => 'KG', 'name' => 'Kilogram', 'allows_decimal' => true],
            ['code' => 'LTR', 'name' => 'Liter', 'allows_decimal' => true],
        ], ['code'], ['name', 'allows_decimal']);

        Warehouse::query()->updateOrCreate(
            ['code' => 'GDG-UTAMA'],
            ['name' => 'Gudang Utama', 'is_active' => true],
        );

        CashAccount::query()->firstOrCreate(
            ['code' => 'KAS-KANTOR'],
            ['name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true],
        );

        CashAccount::query()->firstOrCreate(
            ['code' => 'BANK'],
            ['name' => 'Bank', 'type' => 'bank', 'balance' => 0, 'is_active' => true],
        );
    }
}
