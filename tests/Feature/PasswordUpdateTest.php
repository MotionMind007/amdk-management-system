<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_every_role_can_change_its_own_password_and_activity_is_audited(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Password berhasil diubah.');
        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'CHANGE_PASSWORD',
            'module' => 'Authentication',
            'entity_type' => $user->getMorphClass(),
            'entity_id' => $user->id,
            'old_values' => null,
            'new_values' => null,
        ]);
    }

    public function test_password_page_is_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.password.edit'))
            ->assertOk()
            ->assertSee('Ubah Password');
    }

    public function test_wrong_current_password_is_rejected_without_creating_an_audit_log(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('account.password.edit'))
            ->put(route('account.password.update'), [
                'current_password' => 'password-salah',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]);

        $response->assertRedirect(route('account.password.edit'));
        $response->assertSessionHasErrors([
            'current_password' => 'Password saat ini tidak sesuai.',
        ]);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function roles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            $roles[$role->value] = [$role];
        }

        return $roles;
    }
}
