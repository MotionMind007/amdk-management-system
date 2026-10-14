<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_authenticated_user_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $response = $this->get(route('modules.index'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors([
            'email' => 'Akun Anda sudah dinonaktifkan. Hubungi administrator.',
        ]);
        $this->assertGuest();
    }

    public function test_password_change_invalidates_a_session_using_the_previous_password_hash(): void
    {
        $user = User::factory()->create();
        $previousPasswordHash = $user->password;

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHasNoErrors();

        $this->flushSession();
        $response = $this
            ->withSession(['password_hash_web' => $previousPasswordHash])
            ->actingAs($user->fresh())
            ->get(route('modules.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_new_user_password_requires_at_least_twelve_characters(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'Pengguna Baru',
            'email' => 'pengguna.baru@example.com',
            'role' => UserRole::Sales->value,
            'is_active' => true,
            'password' => 'Pendek12345',
            'password_confirmation' => 'Pendek12345',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'Password minimal 12 karakter.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'pengguna.baru@example.com']);
    }

    #[DataProvider('unsafeLastPrivilegedAccountChanges')]
    public function test_last_privileged_account_cannot_be_demoted_or_deactivated(string $role, bool $isActive, string $errorField): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $response = $this->actingAs($owner)->put(route('admin.users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => $role,
            'is_active' => $isActive,
        ]);

        $response->assertSessionHasErrors([
            $errorField => 'Minimal satu akun Owner atau Super Admin harus tetap aktif.',
        ]);
        $owner->refresh();
        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertTrue($owner->is_active);
    }

    public function test_privileged_account_can_be_demoted_when_another_privileged_account_remains_active(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $superAdministrator = User::factory()->create(['role' => UserRole::SuperAdministrator]);

        $response = $this->actingAs($owner)->put(route('admin.users.update', $superAdministrator), [
            'name' => $superAdministrator->name,
            'email' => $superAdministrator->email,
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame(UserRole::Admin, $superAdministrator->fresh()->role);
    }

    public function test_employee_update_cannot_remove_the_last_privileged_account(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $employee = Employee::factory()->create([
            'user_id' => $owner->id,
            'email' => $owner->email,
            'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->put(route('employees.update', $employee), [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'address' => $employee->address,
            'position' => $employee->position,
            'department' => $employee->department,
            'join_date' => $employee->join_date?->format('Y-m-d'),
            'status' => 'active',
            'role' => UserRole::Admin->value,
        ]);

        $response->assertSessionHasErrors([
            'role' => 'Minimal satu akun Owner atau Super Admin harus tetap aktif.',
        ]);
        $this->assertSame(UserRole::Owner, $owner->fresh()->role);
    }

    public function test_basic_security_headers_are_added_to_web_responses(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_production_https_response_adds_csp_and_hsts_headers(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->get('https://localhost/login');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    /** @return array<string, array{string, bool, string}> */
    public static function unsafeLastPrivilegedAccountChanges(): array
    {
        return [
            'demotion' => [UserRole::Admin->value, true, 'role'],
            'deactivation' => [UserRole::Owner->value, false, 'is_active'],
        ];
    }
}
