<?php

namespace Database\Seeders;

use App\Models\CashAccount;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminPassword = env('ADMIN_PASSWORD');

        if (! is_string($adminPassword) || $adminPassword === '') {
            $this->command?->warn('ADMIN_PASSWORD belum diatur; user administrator tidak dibuat.');
        } else {
            User::query()->updateOrCreate(
                ['email' => env('ADMIN_EMAIL', 'admin@amdk.local')],
                [
                    'name' => env('ADMIN_NAME', 'Administrator'),
                    'password' => $adminPassword,
                    'role' => UserRole::Administrator,
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

        CashAccount::query()->updateOrCreate(
            ['code' => 'KAS-KANTOR'],
            ['name' => 'Kas Kantor', 'type' => 'cash', 'balance' => 0, 'is_active' => true],
        );
    }
}
