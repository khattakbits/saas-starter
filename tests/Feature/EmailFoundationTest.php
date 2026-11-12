<?php

namespace Tests\Feature;

use App\Core\Models\AuditLog;
use App\Core\Models\EmailAccount;
use App\Core\Models\EmailLog;
use App\Core\Support\Mailer;
use App\Core\Support\MailHostGuard;
use App\Core\Support\SettingsStore;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Mailer as LaravelMailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class EmailFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    /** Mail::build() ko array transport (ya throwing transport) par bhej deta hai — koi SMTP nahi */
    private function fakeMail(?string $failure = null): MailManager
    {
        $fake = new class($this->app) extends MailManager
        {
            public array $built = [];

            public ?string $failure = null;

            public function build($config)
            {
                $this->built[] = $config;

                if ($this->failure !== null) {
                    $transport = new class($this->failure) implements TransportInterface
                    {
                        public function __construct(private string $message) {}

                        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
                        {
                            throw new TransportException($this->message);
                        }

                        public function __toString(): string
                        {
                            return 'throwing';
                        }
                    };

                    return new LaravelMailer('ondemand', $this->app['view'], $transport, $this->app['events']);
                }

                return parent::build(['transport' => 'array', 'name' => $config['name'] ?? 'ondemand']);
            }
        };

        $fake->failure = $failure;
        $this->app->instance('mail.manager', $fake);
        Mail::clearResolvedInstance('mail.manager');

        return $fake;
    }

    private function account(array $attrs = []): EmailAccount
    {
        return EmailAccount::create($attrs + [
            'name' => 'Primary',
            'from_email' => 'from@example.com',
            'from_name' => 'Test App',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'p@ss-123',
        ]);
    }

    private function makeUser(array $permissions = [], array $roles = []): User
    {
        $user = User::forceCreate([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password-12345',
            'is_active' => true,
        ]);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    public function test_the_default_account_is_used_to_build_the_transport(): void
    {
        $fake = $this->fakeMail();
        $this->account();

        Mailer::send('to@example.com', 'Hello', 'Body text');

        $this->assertCount(1, $fake->built);
        $config = $fake->built[0];

        $this->assertSame('smtp', $config['transport']);
        $this->assertSame('smtp', $config['scheme']);           // tls = STARTTLS
        $this->assertSame('smtp.example.com', $config['host']);
        $this->assertSame(587, $config['port']);
        $this->assertSame('user@example.com', $config['username']);
        $this->assertSame('p@ss-123', $config['password']);
    }

    public function test_ssl_uses_implicit_tls_and_none_uses_plain_smtp(): void
    {
        $this->assertSame('smtps', Mailer::configFor($this->account(['name' => 'Ssl', 'encryption' => 'ssl']))['scheme']);
        $this->assertSame('smtp', Mailer::configFor($this->account(['name' => 'None', 'encryption' => 'none']))['scheme']);
    }

    public function test_the_message_carries_text_html_from_and_reply_to(): void
    {
        $this->fakeMail();
        $this->account();
        app(SettingsStore::class)->setMany(['app_name' => 'Acme', 'email_footer' => 'Acme footer', 'email_reply_to' => 'reply@example.com']);

        $sent = [];
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent) {
            $sent[] = $event->message;
        });

        Mailer::send('to@example.com', 'Hello there', "First line.\n\nSecond line https://example.com");

        $this->assertCount(1, $sent);
        $message = $sent[0];

        $this->assertSame('from@example.com', $message->getFrom()[0]->getAddress());
        $this->assertSame('Test App', $message->getFrom()[0]->getName());
        $this->assertSame('reply@example.com', $message->getReplyTo()[0]->getAddress());
        $this->assertSame('to@example.com', $message->getTo()[0]->getAddress());
        $this->assertSame('Hello there', $message->getSubject());
        $this->assertStringContainsString('Second line', $message->getTextBody());
        $this->assertStringContainsString('Acme', $message->getHtmlBody());
        $this->assertStringContainsString('Acme footer', $message->getHtmlBody());
        $this->assertStringContainsString('href="https://example.com"', $message->getHtmlBody());
    }

    public function test_the_first_active_account_becomes_default_and_only_one_stays_default(): void
    {
        $first = $this->account(['name' => 'First']);
        $second = $this->account(['name' => 'Second']);

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);

        EmailAccount::makeDefault($second);

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, EmailAccount::query()->where('is_default', true)->count());
    }

    public function test_deleting_the_default_promotes_the_oldest_other_active_account(): void
    {
        $first = $this->account(['name' => 'First']);
        $second = $this->account(['name' => 'Second']);
        $third = $this->account(['name' => 'Third']);

        $first->delete();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($third->fresh()->is_default);
        $this->assertSame(1, EmailAccount::query()->where('is_default', true)->count());
    }

    public function test_the_password_is_encrypted_at_rest_and_never_serialised(): void
    {
        $account = $this->account();

        $raw = DB::table('email_accounts')->where('id', $account->id)->value('password');
        $this->assertNotSame('p@ss-123', $raw);
        $this->assertSame('p@ss-123', $account->fresh()->password);

        $this->assertArrayNotHasKey('password', $account->fresh()->toArray());
        $this->assertStringNotContainsString('p@ss-123', json_encode($account->fresh()->toArray()));
    }

    public function test_the_job_marks_the_log_as_sent(): void
    {
        $this->fakeMail();
        $this->account();

        $log = Mailer::send('to@example.com', 'Hi', 'Body')->fresh();

        $this->assertSame('sent', $log->status);
        $this->assertNotNull($log->sent_at);
        $this->assertSame(1, $log->attempts);
        $this->assertNotNull($log->email_account_id);
    }

    public function test_the_job_marks_the_log_as_failed_without_leaking_credentials(): void
    {
        $this->fakeMail('535 auth failed for user@example.com using p@ss-123');
        $this->account();

        $log = Mailer::send('to@example.com', 'Hi', 'Body')->fresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertStringContainsString('[redacted]', $log->error);
        $this->assertStringNotContainsString('p@ss-123', $log->error);
        $this->assertStringNotContainsString('user@example.com', $log->error);
    }

    public function test_the_daily_limit_blocks_sending_and_logs_a_failure(): void
    {
        $fake = $this->fakeMail();
        $this->account();
        app(SettingsStore::class)->setMany(['email_daily_limit' => 1]);

        $first = Mailer::send('to@example.com', 'One', 'Body')->fresh();
        $second = Mailer::send('to@example.com', 'Two', 'Body')->fresh();

        $this->assertSame('sent', $first->status);
        $this->assertSame('failed', $second->status);
        $this->assertSame('Daily limit reached', $second->error);
        $this->assertCount(1, $fake->built);
    }

    public function test_turning_email_off_blocks_sending(): void
    {
        $fake = $this->fakeMail();
        $this->account();
        app(SettingsStore::class)->setMany(['email_enabled' => '0']);

        $log = Mailer::send('to@example.com', 'Hi', 'Body')->fresh();

        $this->assertSame('failed', $log->status);
        $this->assertSame('Email is turned off', $log->error);
        $this->assertCount(0, $fake->built);
    }

    public function test_the_test_send_endpoint_needs_permission(): void
    {
        $this->fakeMail();
        $account = $this->account();
        $nobody = $this->makeUser();

        $this->actingAs($nobody)->postJson("/settings/email-accounts/{$account->id}/test")->assertForbidden();
        $this->assertSame(0, EmailLog::query()->count());
    }

    public function test_the_test_send_endpoint_validates_the_address(): void
    {
        $this->fakeMail();
        $account = $this->account();
        $admin = $this->makeUser(roles: ['super-admin']);

        $this->actingAs($admin)
            ->postJson("/settings/email-accounts/{$account->id}/test", ['to' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_the_test_send_endpoint_sends_synchronously_and_updates_the_account(): void
    {
        $this->fakeMail();
        $account = $this->account();
        $admin = $this->makeUser(roles: ['super-admin']);

        $this->actingAs($admin)
            ->postJson("/settings/email-accounts/{$account->id}/test", ['to' => 'someone@example.com'])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'error' => null]);

        $fresh = $account->fresh();
        $this->assertTrue($fresh->last_test_ok);
        $this->assertNotNull($fresh->last_tested_at);

        $log = EmailLog::query()->where('template_key', 'test')->sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('someone@example.com', $log->to_email);

        $audit = AuditLog::query()->where('event', 'email_account.tested')->sole();
        $this->assertSame('email_account', $audit->subject_type);
        $this->assertSame($account->name, $audit->subject_label);
        $this->assertStringNotContainsString('p@ss-123', json_encode($audit->meta));
    }

    public function test_the_test_send_endpoint_is_throttled(): void
    {
        $this->fakeMail();
        $account = $this->account();
        $admin = $this->makeUser(roles: ['super-admin']);
        $this->actingAs($admin);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/settings/email-accounts/{$account->id}/test")->assertOk();
        }

        $this->postJson("/settings/email-accounts/{$account->id}/test")->assertStatus(429);
    }

    public function test_pruning_respects_the_retention_days(): void
    {
        $old = $this->log(now()->subDays(120));
        $recent = $this->log(now()->subDays(10));

        app(SettingsStore::class)->setMany(['email_log_days' => 90]);
        $this->artisan('emails:prune')->assertSuccessful();

        $this->assertDatabaseMissing('email_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('email_logs', ['id' => $recent->id]);
    }

    public function test_pruning_is_off_when_days_is_zero(): void
    {
        $old = $this->log(now()->subDays(400));

        app(SettingsStore::class)->setMany(['email_log_days' => 0]);
        $this->artisan('emails:prune')->assertSuccessful();

        $this->assertDatabaseHas('email_logs', ['id' => $old->id]);
    }

    public function test_the_host_guard_blocks_loopback_link_local_and_unresolvable_hosts(): void
    {
        $this->assertNotNull(MailHostGuard::error('127.0.0.1'));
        $this->assertNotNull(MailHostGuard::error('169.254.169.254'));
        $this->assertNotNull(MailHostGuard::error('::1'));
        $this->assertNotNull(MailHostGuard::error('fe80::1'));
        $this->assertNotNull(MailHostGuard::error('does-not-exist.invalid'));
        $this->assertNull(MailHostGuard::error('203.0.113.10'));
    }

    public function test_the_body_is_never_stored(): void
    {
        $this->fakeMail();
        $this->account();

        $log = Mailer::send('to@example.com', 'Subject line', 'SECRET BODY TEXT')->fresh();
        $row = DB::table('email_logs')->where('id', $log->id)->first();

        $this->assertStringNotContainsString('SECRET BODY', json_encode($row));
    }

    public function test_mail_readiness_is_shared_with_inertia(): void
    {
        $admin = $this->makeUser(roles: ['super-admin']);

        $this->actingAs($admin)->get('/examples/list')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('app.mailReady', false));

        $this->account();
        Mailer::forgetReady();

        $this->actingAs($admin)->get('/examples/list')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('app.mailReady', true));
    }

    private function log($createdAt): EmailLog
    {
        return EmailLog::create([
            'to_email' => 'old@example.com',
            'subject' => 'Old mail',
            'status' => 'sent',
            'attempts' => 1,
            'queued_at' => $createdAt,
            'created_at' => $createdAt,
        ]);
    }
}
