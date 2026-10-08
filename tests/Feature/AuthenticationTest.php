<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/modules')->assertRedirect(route('login'));
    }

    public function test_active_user_can_login_and_activity_is_audited(): void
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $response = $this->post('/login', [
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('modules.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'LOGIN',
            'module' => 'Authentication',
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);
        $this->assertGuest();
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
