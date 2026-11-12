<?php

namespace Tests\Feature;

use App\Core\Models\EmailLog;
use App\Core\Models\EmailTemplate;
use App\Core\Support\EmailTemplates;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
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

    private function templates(): EmailTemplates
    {
        return app(EmailTemplates::class);
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

    private function override(array $attrs = []): EmailTemplate
    {
        return EmailTemplate::create($attrs + [
            'key' => 'password_reset',
            'subject' => 'Custom {{ app_name }} subject',
            'body' => 'Custom body {{ reset_url }}',
            'is_active' => true,
        ]);
    }

    public function test_the_default_template_renders_with_the_given_data(): void
    {
        $rendered = $this->templates()->render('password_reset', [
            'user_name' => 'Ali Raza',
            'app_name' => 'Acme',
            'reset_url' => 'https://acme.test/reset/xyz',
            'expires_minutes' => 60,
        ]);

        $this->assertSame('Reset your Acme password', $rendered['subject']);
        $this->assertStringContainsString('Hi Ali Raza,', $rendered['text']);
        $this->assertStringContainsString('https://acme.test/reset/xyz', $rendered['text']);
        $this->assertStringContainsString('60 minutes', $rendered['text']);
        $this->assertStringNotContainsString('{{', $rendered['text']);
    }

    public function test_an_override_wins_and_an_inactive_override_falls_back_to_the_default(): void
    {
        $this->override();
        $data = ['app_name' => 'Acme', 'reset_url' => 'https://acme.test/r', 'user_name' => 'Ali', 'expires_minutes' => 5];

        $rendered = $this->templates()->render('password_reset', $data);
        $this->assertSame('Custom Acme subject', $rendered['subject']);
        $this->assertSame('Custom body https://acme.test/r', $rendered['text']);

        EmailTemplate::query()->where('key', 'password_reset')->update(['is_active' => false]);

        $rendered = $this->templates()->render('password_reset', $data);
        $this->assertSame('Reset your Acme password', $rendered['subject']);
        $this->assertStringContainsString('Hi Ali,', $rendered['text']);
    }

    public function test_an_unknown_placeholder_is_rejected_on_save(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email-templates/password_reset', [
            'subject' => 'Hi {{ user_name }}',
            'body' => 'Body {{ nope }} and {{ reset_url }}',
            'is_active' => true,
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('email_templates', ['key' => 'password_reset']);
    }

    public function test_a_missing_required_placeholder_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email-templates/password_reset', [
            'subject' => 'Hi {{ user_name }}',
            'body' => 'There is no link in this body.',
            'is_active' => true,
        ])->assertSessionHasErrors('body');

        $this->assertDatabaseMissing('email_templates', ['key' => 'password_reset']);
    }

    public function test_a_subject_with_a_line_break_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email-templates/password_reset', [
            'subject' => "Line one\nLine two",
            'body' => 'Body {{ reset_url }}',
        ])->assertSessionHasErrors('subject');
    }

    public function test_rendering_never_executes_injected_code(): void
    {
        $this->override([
            'subject' => 'Injected',
            'body' => "{{ system('id') }}\n@php echo 1; @endphp\n<?php echo 1; ?>\n{{ reset_url }}",
        ]);

        $rendered = $this->templates()->render('password_reset', ['reset_url' => 'https://acme.test/r']);

        $this->assertStringContainsString("{{ system('id') }}", $rendered['text']);
        $this->assertStringContainsString('@php echo 1; @endphp', $rendered['text']);
        $this->assertStringContainsString('<?php echo 1; ?>', $rendered['text']);
        $this->assertStringContainsString('https://acme.test/r', $rendered['text']);
    }

    public function test_the_rendered_subject_is_stripped_to_one_line(): void
    {
        $this->override(['key' => 'test', 'subject' => "Line one\r\nLine two", 'body' => 'Body']);

        $rendered = $this->templates()->render('test', []);

        $this->assertSame('Line one Line two', $rendered['subject']);
        $this->assertStringNotContainsString("\n", $rendered['subject']);
        $this->assertStringNotContainsString("\r", $rendered['subject']);
    }

    public function test_reset_deletes_the_override_and_falls_back_to_the_default(): void
    {
        $admin = $this->admin();
        $this->override(['key' => 'test', 'subject' => 'Custom subject', 'body' => 'Custom body']);

        $this->actingAs($admin)->delete('/settings/email-templates/test')->assertRedirect();

        $this->assertDatabaseMissing('email_templates', ['key' => 'test']);
        $this->assertSame('Test email from Acme', $this->templates()->render('test', ['app_name' => 'Acme'])['subject']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'email_template.reset']);
    }

    public function test_saving_an_override_writes_the_audit_entry(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/settings/email-templates/password_reset', [
            'subject' => 'Custom {{ app_name }}',
            'body' => 'Body {{ reset_url }}',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('email_templates', ['key' => 'password_reset', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'email_template.updated', 'subject_label' => 'Password reset']);
    }

    public function test_preview_and_update_need_permissions(): void
    {
        $viewer = $this->makeUser(['settings.email_templates.view']);

        $this->actingAs($viewer)->get('/settings/email-templates')->assertOk();
        $this->actingAs($viewer)->get('/settings/email-templates/password_reset')->assertOk();
        $this->actingAs($viewer)->postJson('/settings/email-templates/password_reset/preview')->assertForbidden();
        $this->actingAs($viewer)->put('/settings/email-templates/password_reset', [])->assertForbidden();
        $this->actingAs($viewer)->delete('/settings/email-templates/password_reset')->assertForbidden();
        $this->actingAs($viewer)->post('/settings/email-templates/password_reset/test')->assertForbidden();

        $nobody = $this->makeUser();
        $this->actingAs($nobody)->get('/settings/email-templates')->assertForbidden();
    }

    public function test_the_preview_endpoint_renders_the_draft(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/settings/email-templates/password_reset/preview', [
            'subject' => 'Draft {{ app_name }}',
            'body' => 'Draft body {{ reset_url }}',
        ])->assertOk()
            ->assertJsonPath('subject', 'Draft '.config('app.name'))
            ->assertJsonPath('text', 'Draft body https://example.com/reset/8f2c1d');
    }

    public function test_send_test_uses_the_template_and_logs_the_email(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/email-templates/test/test')->assertRedirect();

        $log = EmailLog::query()->where('template_key', 'test')->sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame($admin->email, $log->to_email);
        $this->assertSame('Test email from '.config('app.name'), $log->subject);
    }

    public function test_a_module_registered_template_appears_in_the_list(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/settings/email-templates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/EmailTemplates/Index')
                ->where('templates', fn ($rows) => collect($rows)->pluck('key')->all() === [
                    'demo_entry_submitted', 'password_reset', 'test',
                ]));

        $this->actingAs($admin)->get('/settings/email-templates/demo_entry_submitted')->assertOk();
    }
}
