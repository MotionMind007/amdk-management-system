<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_its_employee_profile_and_password_link(): void
    {
        $user = User::factory()->create([
            'name' => 'Yohanes Wenda',
            'email' => 'yohanes@example.com',
        ]);
        Employee::factory()->create([
            'user_id' => $user->id,
            'name' => 'Yohanes Wenda',
            'email' => 'yohanes@example.com',
            'employee_code' => 'EMP-001',
            'position' => 'Operator Mesin',
            'department' => 'Produksi',
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();
        $response->assertSeeText('Yohanes Wenda');
        $response->assertSeeText('EMP-001');
        $response->assertSeeText('Operator Mesin');
        $response->assertSee(route('account.password.edit'), false);
    }

    public function test_profile_does_not_display_another_employees_data(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        Employee::factory()->create([
            'email' => 'orang-lain@example.com',
            'employee_code' => 'EMP-RAHASIA',
        ]);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();
        $response->assertSeeText('Data master karyawan belum terhubung.');
        $response->assertDontSeeText('EMP-RAHASIA');
    }

    public function test_guest_cannot_view_employee_profile(): void
    {
        $response = $this->get(route('profile.show'));

        $response->assertRedirect(route('login'));
    }
}
