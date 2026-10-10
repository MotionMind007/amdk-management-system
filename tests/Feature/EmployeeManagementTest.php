<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_an_employee_with_a_linked_login_account_and_activity_is_audited(): void
    {
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->post(route('employees.store'), [
            'employee_code' => 'EMP-001',
            'name' => 'Yohanes Wenda',
            'phone' => '081234567890',
            'email' => 'yohanes@example.com',
            'address' => 'Jayapura',
            'position' => 'Operator Mesin',
            'department' => 'Produksi',
            'join_date' => '2026-10-01',
            'status' => 'active',
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
        ]);

        $employee = Employee::query()->where('employee_code', 'EMP-001')->firstOrFail();
        $employeeUser = User::query()->where('email', 'yohanes@example.com')->firstOrFail();
        $response->assertRedirect(route('employees.index'));
        $this->assertSame('Yohanes Wenda', $employee->name);
        $this->assertSame($employeeUser->id, $employee->user_id);
        $this->assertSame(UserRole::Warehouse, $employeeUser->role);
        $this->assertTrue(Hash::check('password-awal', $employeeUser->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE',
            'module' => 'User',
            'entity_id' => $employeeUser->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'CREATE',
            'module' => 'Employee',
            'entity_id' => $employee->id,
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => 'yohanes@example.com',
            'password' => 'password-awal',
        ])->assertRedirect(route('modules.index'));
        $this->assertAuthenticatedAs($employeeUser);
    }

    public function test_administrator_can_update_an_employee(): void
    {
        $administrator = User::factory()->administrator()->create();
        $employee = Employee::factory()->create([
            'employee_code' => 'EMP-001',
            'position' => 'Operator Mesin',
            'department' => 'Produksi',
        ]);

        $response = $this->actingAs($administrator)->put(route('employees.update', $employee), [
            'employee_code' => 'EMP-001',
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'address' => $employee->address,
            'position' => 'Kepala Shift',
            'department' => 'Produksi',
            'join_date' => '2025-01-15',
            'status' => 'active',
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'position' => 'Kepala Shift',
            'join_date' => '2025-01-15 00:00:00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $administrator->id,
            'action' => 'UPDATE',
            'module' => 'Employee',
            'entity_id' => $employee->id,
        ]);
    }

    public function test_administrator_can_create_an_employee_without_a_join_date(): void
    {
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->post(route('employees.store'), [
            'employee_code' => 'EMP-002',
            'name' => 'Marta Kogoya',
            'email' => 'marta@example.com',
            'position' => 'Staf Gudang',
            'department' => 'Gudang',
            'status' => 'active',
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'employee_code' => 'EMP-002',
            'join_date' => null,
        ]);
    }

    public function test_administrator_can_upload_an_optional_employee_photo(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->post(route('employees.store'), [
            'employee_code' => 'EMP-003',
            'name' => 'Maria Kogoya',
            'email' => 'maria@example.com',
            'position' => 'Operator Produksi',
            'department' => 'Produksi',
            'status' => 'active',
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
            'photo' => UploadedFile::fake()->image('maria.jpg', 400, 400),
        ]);

        $employee = Employee::query()->where('employee_code', 'EMP-003')->firstOrFail();
        $response->assertRedirect(route('employees.index'));
        $this->assertNotNull($employee->photo_path);
        Storage::disk('public')->assertExists($employee->photo_path);
    }

    public function test_non_image_employee_photo_is_rejected(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'employee_code' => 'EMP-004',
                'name' => 'File Berbahaya',
                'email' => 'berbahaya@example.com',
                'position' => 'Operator',
                'department' => 'Produksi',
                'status' => 'active',
                'role' => UserRole::Warehouse->value,
                'password' => 'password-awal',
                'password_confirmation' => 'password-awal',
                'photo' => UploadedFile::fake()->create('payload.php', 20, 'application/x-php'),
            ]);

        $response->assertRedirect(route('employees.create'));
        $response->assertSessionHasErrors([
            'photo' => 'Foto karyawan harus berupa gambar.',
        ]);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-004']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_updating_employee_photo_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $administrator = User::factory()->administrator()->create();
        $oldPhotoPath = UploadedFile::fake()->image('lama.jpg')->store('employees', 'public');
        $employee = Employee::factory()->create(['photo_path' => $oldPhotoPath]);

        $response = $this->actingAs($administrator)->put(route('employees.update', $employee), [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => $employee->email,
            'address' => $employee->address,
            'position' => $employee->position,
            'department' => $employee->department,
            'join_date' => $employee->join_date?->format('Y-m-d'),
            'status' => $employee->status,
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
            'photo' => UploadedFile::fake()->image('baru.png'),
        ]);

        $newPhotoPath = $employee->fresh()->photo_path;
        $response->assertRedirect(route('employees.index'));
        $this->assertNotSame($oldPhotoPath, $newPhotoPath);
        Storage::disk('public')->assertMissing($oldPhotoPath);
        Storage::disk('public')->assertExists($newPhotoPath);
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_roles_without_employee_permission_cannot_view_employee_data(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get(route('employees.index'))->assertForbidden();
    }

    public function test_non_administrator_cannot_create_an_employee(): void
    {
        $user = User::factory()->create(['role' => UserRole::Warehouse]);

        $response = $this->actingAs($user)->post(route('employees.store'), [
            'employee_code' => 'EMP-001',
            'name' => 'Karyawan Baru',
            'position' => 'Operator',
            'department' => 'Produksi',
            'join_date' => '2026-10-01',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-001']);
    }

    public function test_admin_can_access_employee_management_without_system_administration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('employees.index'))->assertOk();
        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSeeText('Admin')
            ->assertSeeText('Gudang')
            ->assertSeeText('Sales')
            ->assertDontSeeText('Super Admin')
            ->assertDontSeeText('Owner');
        $this->actingAs($admin)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_cannot_create_a_privileged_employee_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'employee_code' => 'EMP-PRIVILEGED',
                'name' => 'Privileged User',
                'email' => 'privileged@example.com',
                'position' => 'Pimpinan',
                'department' => 'Direksi',
                'status' => 'active',
                'role' => UserRole::SuperAdministrator->value,
                'password' => 'password-awal',
                'password_confirmation' => 'password-awal',
            ]);

        $response->assertRedirect(route('employees.create'));
        $response->assertSessionHasErrors([
            'role' => 'Role login tersebut tidak dapat dipilih.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'privileged@example.com']);
        $this->assertDatabaseMissing('employees', ['employee_code' => 'EMP-PRIVILEGED']);
    }

    public function test_admin_cannot_edit_an_employee_with_a_super_administrator_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superAdministrator = User::factory()->superAdministrator()->create();
        $employee = Employee::factory()->create(['user_id' => $superAdministrator->id]);

        $this->actingAs($admin)
            ->get(route('employees.edit', $employee))
            ->assertForbidden();
    }

    public function test_duplicate_employee_code_is_rejected_with_a_clear_message(): void
    {
        $administrator = User::factory()->administrator()->create();
        Employee::factory()->create(['employee_code' => 'EMP-001']);

        $response = $this->actingAs($administrator)->from(route('employees.create'))->post(route('employees.store'), [
            'employee_code' => 'EMP-001',
            'name' => 'Karyawan Lain',
            'email' => 'karyawan-lain@example.com',
            'position' => 'Operator',
            'department' => 'Produksi',
            'join_date' => '2026-10-01',
            'status' => 'active',
            'role' => UserRole::Warehouse->value,
            'password' => 'password-awal',
            'password_confirmation' => 'password-awal',
        ]);

        $response->assertRedirect(route('employees.create'));
        $response->assertSessionHasErrors([
            'employee_code' => 'Kode karyawan sudah digunakan.',
        ]);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_employee_names_are_escaped_in_the_list(): void
    {
        $administrator = User::factory()->administrator()->create();
        Employee::factory()->create(['name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($administrator)->get(route('employees.index'));

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function nonAdministratorRoles(): array
    {
        $roles = [];

        foreach (UserRole::cases() as $role) {
            if (! in_array($role, [UserRole::Admin, UserRole::SuperAdministrator, UserRole::Owner], true)) {
                $roles[$role->value] = [$role];
            }
        }

        return $roles;
    }
}
