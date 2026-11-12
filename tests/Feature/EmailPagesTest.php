<?php

namespace Tests\Feature;

use App\Core\Models\EmailAccount;
use App\Core\Models\EmailLog;
use App\Core\Support\SettingsStore;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailPagesTest extends TestCase
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

    private function admin(): User
    {
        return $this->makeUser(roles: ['super-admin']);
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

    private function emailLog(array $attrs = []): EmailLog
    {
        return EmailLog::create($attrs + [
            'to_email' => 'to@example.com',
            'subject' => 'Hello',
            'template_key' => 'test',
            'status' => 'sent',
            'attempts' => 1,
            'queued_at' => now(),
            'created_at' => now(),
        ]);
    }

    /** Valid payload for the account form (host is a public TEST-NET address, so no DNS is needed) */
    private function payload(array $attrs = []): array
    {
        return $attrs + [
            'name' => 'Primary',
            'from_email' => 'from@example.com',
            'from_name' => 'Test App',
            'host' => '203.0.113.10',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'p@ss-123',
            'is_active' => true,
            'is_default' => true,
        ];
    }

    public function test_a_user_without_permissions_gets_403_on_every_email_route(): void
    {
        $account = $this->account();
        $log = $this->emailLog();
        $nobody = $this->makeUser();

        $this->actingAs($nobody);
        $this->get('/settings/email-accounts')->assertForbidden();
        $this->get('/settings/email-accounts/data')->assertForbidden();
        $this->get('/settings/email-accounts/create')->assertForbidden();
        $this->get("/settings/email-accounts/{$account->id}")->assertForbidden();
        $this->put("/settings/email-accounts/{$account->id}", [])->assertForbidden();
        $this->delete("/settings/email-accounts/{$account->id}")->assertForbidden();
        $this->post("/settings/email-accounts/{$account->id}/test")->assertForbidden();
        $this->get('/settings/email-log')->assertForbidden();
        $this->get('/settings/email-log/data')->assertForbidden();
        $this->get("/settings/email-log/{$log->id}")->assertForbidden();
        $this->get('/settings/email')->assertForbidden();
    }

    public function test_create_update_and_delete_need_their_own_permission(): void
    {
        $account = $this->account();
        $viewer = $this->makeUser(['settings.email_accounts.view']);

        $this->actingAs($viewer)->get('/settings/email-accounts')->assertOk();
        $this->actingAs($viewer)->get('/settings/email-accounts/data')->assertOk();

        $this->actingAs($viewer)->get('/settings/email-accounts/create')->assertForbidden();
        $this->actingAs($viewer)->post('/settings/email-accounts', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->put("/settings/email-accounts/{$account->id}", $this->payload())->assertForbidden();
        $this->actingAs($viewer)->delete("/settings/email-accounts/{$account->id}")->assertForbidden();

        $this->assertSame(1, EmailAccount::query()->count());
    }

    public function test_an_account_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/email-accounts', $this->payload())->assertRedirect();
        $account = EmailAccount::sole();
        $this->assertTrue($account->is_default);
        $this->assertSame('p@ss-123', $account->password);

        $this->actingAs($admin)
            ->put("/settings/email-accounts/{$account->id}", $this->payload(['name' => 'Renamed']))
            ->assertRedirect();
        $this->assertSame('Renamed', $account->fresh()->name);

        $this->actingAs($admin)->delete("/settings/email-accounts/{$account->id}")->assertRedirect();
        $this->assertSame(0, EmailAccount::query()->count());

        $this->assertDatabaseHas('audit_logs', ['event' => 'email_account.created', 'subject_label' => 'Primary']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'email_account.updated', 'subject_label' => 'Renamed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'email_account.deleted', 'subject_label' => 'Renamed']);
    }

    public function test_a_blank_password_on_update_keeps_the_stored_one(): void
    {
        $admin = $this->admin();
        $account = $this->account(['password' => 'old-secret']);

        $this->actingAs($admin)
            ->put("/settings/email-accounts/{$account->id}", $this->payload(['password' => '']))
            ->assertRedirect();

        $this->assertSame('old-secret', $account->fresh()->password);

        $this->actingAs($admin)
            ->put("/settings/email-accounts/{$account->id}", $this->payload(['password' => 'new-secret']))
            ->assertRedirect();

        $this->assertSame('new-secret', $account->fresh()->password);
    }

    public function test_the_password_never_appears_in_the_props(): void
    {
        $admin = $this->admin();
        $account = $this->account(['password' => 'p@ss-123']);

        $page = $this->actingAs($admin)->get("/settings/email-accounts/{$account->id}")->assertOk();
        $page->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Settings/EmailAccounts/Form')
            ->where('account.has_password', true)
            ->missing('account.password'));

        $this->assertStringNotContainsString('p@ss-123', $page->getContent());

        $list = $this->actingAs($admin)->get('/settings/email-accounts/data')->assertOk();
        $this->assertStringNotContainsString('p@ss-123', $list->getContent());
    }

    public function test_marking_an_account_default_switches_the_single_default(): void
    {
        $admin = $this->admin();
        $first = $this->account(['name' => 'First']);
        $second = $this->account(['name' => 'Second']);

        $this->assertTrue($first->fresh()->is_default);

        $this->actingAs($admin)
            ->put("/settings/email-accounts/{$second->id}", $this->payload(['name' => 'Second']))
            ->assertRedirect();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, EmailAccount::query()->where('is_default', true)->count());
    }

    public function test_a_loopback_host_is_rejected_with_a_field_error(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/settings/email-accounts', $this->payload(['host' => '127.0.0.1']))
            ->assertSessionHasErrors('host');

        $this->assertSame(0, EmailAccount::query()->count());
    }

    public function test_the_email_log_list_and_show_never_expose_a_body(): void
    {
        $admin = $this->admin();
        $log = $this->emailLog(['subject' => 'SECRET BODY', 'status' => 'failed', 'error' => 'Connection refused']);

        $list = $this->actingAs($admin)->get('/settings/email-log/data')->assertOk();
        $row = $list->json('data.0');
        $this->assertArrayNotHasKey('body', $row);
        $this->assertArrayNotHasKey('text', $row);
        $this->assertArrayNotHasKey('html', $row);

        $this->actingAs($admin)->get("/settings/email-log/{$log->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('Settings/EmailLog/Show')
                ->where('log.error', 'Connection refused')
                ->missing('log.body')
                ->missing('log.text'));
    }

    public function test_the_email_settings_reject_a_negative_daily_limit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email', [
            'email_enabled' => true,
            'email_reply_to' => '',
            'email_footer' => '',
            'email_daily_limit' => -1,
            'email_log_days' => 90,
        ])->assertSessionHasErrors('email_daily_limit');
    }

    public function test_email_settings_are_saved_and_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email', [
            'email_enabled' => false,
            'email_reply_to' => 'reply@example.com',
            'email_footer' => 'Sent by Acme',
            'email_daily_limit' => 250,
            'email_log_days' => 30,
        ])->assertRedirect();

        $store = app(SettingsStore::class);
        $this->assertSame('0', $store->get('email_enabled'));
        $this->assertSame('reply@example.com', $store->get('email_reply_to'));
        $this->assertSame('250', $store->get('email_daily_limit'));

        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated', 'subject_label' => 'Email settings']);
    }

    public function test_the_email_settings_page_reports_readiness(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/settings/email')
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('Settings/Email/Show')
                ->where('mailReady', false));

        $this->account();

        $this->actingAs($admin)->get('/settings/email')
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia->where('mailReady', true));
    }
}
