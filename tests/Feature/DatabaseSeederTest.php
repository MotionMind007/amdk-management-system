<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_initial_administrator_requires_a_twelve_character_password(): void
    {
        config()->set('operations.initial_admin.password', 'pendek123');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Atur ADMIN_PASSWORD minimal 12 karakter');

        $this->seed(DatabaseSeeder::class);
    }

    public function test_initial_administrator_is_created_from_application_configuration(): void
    {
        config()->set('operations.initial_admin', [
            'name' => 'Super Admin Awal',
            'email' => 'initial-admin@example.com',
            'password' => 'PasswordAwal!2026',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'name' => 'Super Admin Awal',
            'email' => 'initial-admin@example.com',
            'is_active' => true,
        ]);
        $this->assertTrue(User::query()->where('email', 'initial-admin@example.com')->firstOrFail()->role->allows('*'));
    }
}
