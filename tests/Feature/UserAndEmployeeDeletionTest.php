<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAndEmployeeDeletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_remove_a_user_access_without_deleting_transaction_history(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $salesUser = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);

        $this->actingAs($owner)
            ->delete(route('admin.users.destroy', $salesUser))
            ->assertRedirect();

        $this->assertFalse($salesUser->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DEACTIVATE',
            'module' => 'User',
            'entity_id' => $salesUser->id,
        ]);
    }

    public function test_owner_cannot_remove_the_last_privileged_user_access(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)
            ->delete(route('admin.users.destroy', $owner))
            ->assertUnprocessable();

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_owner_can_delete_employee_and_deactivate_linked_login(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $salesUser = User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
        $photoPath = 'employees/karyawan.jpg';
        Storage::disk('public')->put($photoPath, 'photo');
        $employee = Employee::factory()->create(['user_id' => $salesUser->id, 'photo_path' => $photoPath]);

        $this->actingAs($owner)
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertModelMissing($employee);
        $this->assertFalse($salesUser->fresh()->is_active);
        Storage::disk('public')->assertMissing($photoPath);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE',
            'module' => 'Employee',
            'entity_id' => $employee->id,
        ]);
    }
}
