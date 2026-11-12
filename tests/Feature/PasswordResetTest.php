<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use KhattakBits\Core\Models\AuditLog;
use KhattakBits\Core\Models\EmailAccount;
use KhattakBits\Core\Models\EmailLog;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEUTRAL = 'If an account exists for that email, a reset link has been sent.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    private function user(array $attrs = []): User
    {
        return User::forceCreate($attrs + [
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => 'password-12345',
            'is_active' => true,
        ]);
    }

    private function resetPayload(string $token, string $email, string $password = 'new-password-123'): array
    {
        return [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_unknown_disabled_and_valid_emails_get_the_same_neutral_response(): void
    {
        $this->user(['email' => 'active@example.com']);
        $this->user(['email' => 'disabled@example.com', 'is_active' => false]);

        foreach (['nobody@example.com', 'disabled@example.com', 'active@example.com'] as $email) {
            $this->post('/forgot-password', ['email' => $email])
                ->assertStatus(302)
                ->assertSessionHas('success', self::NEUTRAL);
        }
    }

    public function test_a_mail_log_is_written_only_for_the_active_user(): void
    {
        $active = $this->user(['email' => 'active@example.com']);
        $this->user(['email' => 'disabled@example.com', 'is_active' => false]);

        $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        $this->assertSame(0, EmailLog::query()->count());

        $this->post('/forgot-password', ['email' => 'disabled@example.com']);
        $this->assertSame(0, EmailLog::query()->count());

        $this->post('/forgot-password', ['email' => 'active@example.com']);

        $log = EmailLog::query()->sole();
        $this->assertSame('password_reset', $log->template_key);
        $this->assertSame('active@example.com', $log->to_email);
        $this->assertSame($active->id, $log->user_id);
        $this->assertSame('sent', $log->status);
    }

    public function test_the_token_and_the_link_are_never_stored_in_logs(): void
    {
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email]);

        $log = EmailLog::query()->sole();
        $this->assertArrayNotHasKey('body', $log->toArray());
        $this->assertStringNotContainsString('reset-password', json_encode($log->toArray()));
        $this->assertStringNotContainsString('http', json_encode($log->toArray()));

        $audit = AuditLog::query()->where('event', 'auth.password_requested')->sole();
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertNull($audit->meta);
        $this->assertStringNotContainsString('reset-password', json_encode($audit->toArray()));
    }

    public function test_a_token_can_be_used_only_once_and_does_not_log_the_user_in(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);
        $payload = $this->resetPayload($token, $user->email);

        $this->post('/reset-password', $payload)->assertRedirect('/login');

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.password_reset', 'subject_id' => $user->id]);

        $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();

        $this->post('/reset-password', $this->resetPayload($token, $user->email))
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_a_wrong_email_with_a_valid_token_is_rejected(): void
    {
        $user = $this->user();
        $this->user(['email' => 'other@example.com']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', $this->resetPayload($token, 'other@example.com'))
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_the_password_rules_are_enforced(): void
    {
        $user = $this->user();
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', $this->resetPayload($token, $user->email, 'short'))
            ->assertSessionHasErrors('password');

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'different-123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password-12345', $user->fresh()->password));
    }

    public function test_the_throttle_stops_repeated_requests(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        }

        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }

    public function test_the_login_page_reports_whether_mail_is_ready(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('app.mailReady', false));

        EmailAccount::create([
            'name' => 'Primary',
            'from_email' => 'from@example.com',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'p@ss-123',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('app.mailReady', true));
    }

    public function test_the_reset_form_carries_the_token_and_email_from_the_link(): void
    {
        $this->get('/reset-password/abc123?email=user%40example.com')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetPassword')
                ->where('token', 'abc123')
                ->where('email', 'user@example.com'));
    }
}
