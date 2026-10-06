<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_guests_are_redirected_from_the_internal_app(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->get('/app/users')->assertRedirect('/login');
    }

    public function test_user_can_sign_in_and_is_recorded(): void
    {
        $user = $this->userWithRole('Viewer', ['email' => 'viewer@example.com']);

        $this->post('/login', ['email' => 'Viewer@Example.com', 'password' => 'password'])
            ->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $this->userWithRole('Viewer', ['email' => 'viewer@example.com']);

        $this->from('/login')->post('/login', ['email' => 'viewer@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(AuditLog::where('event', 'login_failed')->exists());
    }

    public function test_deactivated_user_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact an administrator.']);
        $this->assertGuest();
    }

    public function test_user_deactivated_mid_session_is_signed_out(): void
    {
        $user = $this->userWithRole('Viewer');
        $this->actingAs($user)->get('/app')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/app')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $this->userWithRole('Viewer', ['email' => 'viewer@example.com']);

        foreach (range(1, 5) as $ignored) {
            $this->post('/login', ['email' => 'viewer@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'viewer@example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_user_can_sign_out(): void
    {
        $user = $this->userWithRole('Viewer');

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'logout', 'user_id' => $user->id]);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->userWithRole('Viewer', ['email' => 'viewer@example.com']);

        $this->post('/forgot-password', ['email' => 'viewer@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'viewer@example.com',
            'password' => 'N3w-strong-pass',
            'password_confirmation' => 'N3w-strong-pass',
        ])->assertRedirect('/login');

        $this->post('/login', ['email' => 'viewer@example.com', 'password' => 'N3w-strong-pass'])->assertRedirect(route('app.dashboard'));
    }

    public function test_password_reset_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', 'If that email belongs to an active account, a reset link is on its way.');

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }
}
