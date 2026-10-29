<?php

namespace Tests\Feature;

use App\Core\Models\Attachment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityTest extends TestCase
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

    private function makeUser(array $permissions = [], array $roles = [], bool $active = true): User
    {
        $user = User::forceCreate([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password-12345',
            'is_active' => $active,
        ]);

        if ($permissions) {
            $user->givePermissionTo($permissions);
        }
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    /* ---------- errors ---------- */

    public function test_unknown_url_is_404_not_500(): void
    {
        $this->get('/nope')->assertNotFound();
    }

    public function test_missing_permission_is_403_not_500(): void
    {
        $this->actingAs($this->makeUser())->get('/settings/users')->assertForbidden();
    }

    public function test_wrong_password_is_a_validation_error(): void
    {
        $user = $this->makeUser();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
    }

    /* ---------- login ---------- */

    public function test_login_throttle_cannot_be_bypassed_by_rotating_emails(): void
    {
        $statuses = [];
        for ($i = 0; $i < 25; $i++) {
            $statuses[] = $this->post('/login', ['email' => "rotating{$i}@example.com", 'password' => 'x'])->status();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_disabled_user_is_logged_out(): void
    {
        $user = $this->makeUser(active: false);

        $this->actingAs($user)->get('/profile')->assertRedirect('/login');
    }

    public function test_admin_create_works_on_a_database_without_the_super_admin_role(): void
    {
        Role::where('name', 'super-admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->artisan('admin:create', ['email' => 'boss@example.com'])
            ->expectsQuestion('Password (kam az kam 10 characters)', 'long-secret-pass')
            ->assertSuccessful();

        $this->assertTrue(User::where('email', 'boss@example.com')->firstOrFail()->hasRole('super-admin'));
    }

    /* ---------- privilege escalation ---------- */

    public function test_role_manager_cannot_edit_a_role_they_hold(): void
    {
        $role = Role::create(['name' => 'role-admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['settings.roles.view', 'settings.roles.update']);
        $user = $this->makeUser(roles: ['role-admin']);

        $this->actingAs($user)->from('/settings/roles')
            ->put("/settings/roles/{$role->id}", ['name' => 'role-admin', 'permissions' => Permission::pluck('name')->all()])
            ->assertForbidden();

        $this->assertCount(2, $role->fresh()->permissions);
    }

    public function test_role_manager_cannot_grant_permissions_they_do_not_hold(): void
    {
        $actor = $this->makeUser(['settings.roles.update']);
        $target = Role::create(['name' => 'target', 'guard_name' => 'web']);

        $this->actingAs($actor)->from('/settings/roles')
            ->put("/settings/roles/{$target->id}", ['name' => 'target', 'permissions' => ['settings.users.update']])
            ->assertSessionHasErrors('permissions');

        $this->assertCount(0, $target->fresh()->permissions);
    }

    public function test_user_manager_cannot_give_themselves_a_stronger_role(): void
    {
        $actor = $this->makeUser(['settings.users.update']);

        $this->actingAs($actor)->from('/settings/users')
            ->put("/settings/users/{$actor->id}", [
                'name' => $actor->name,
                'email' => $actor->email,
                'is_active' => true,
                'roles' => ['manager'],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($actor->fresh()->hasRole('manager'));
    }

    public function test_user_manager_cannot_take_over_a_more_powerful_account(): void
    {
        $actor = $this->makeUser(['settings.users.update']);
        $victim = $this->makeUser(roles: ['manager']);

        $this->actingAs($actor)->put("/settings/users/{$victim->id}", [
            'name' => 'x',
            'email' => $victim->email,
            'is_active' => true,
            'roles' => ['manager'],
            'password' => 'new-password-12345',
            'password_confirmation' => 'new-password-12345',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password-12345', $victim->fresh()->password));
    }

    /* ---------- data integrity ---------- */

    public function test_partial_update_keeps_active_flag_and_roles(): void
    {
        $admin = $this->makeUser(roles: ['super-admin']);
        $target = $this->makeUser(roles: ['viewer']);

        $this->actingAs($admin)
            ->put("/settings/users/{$target->id}", ['name' => 'Renamed', 'email' => $target->email])
            ->assertRedirect();

        $target = $target->fresh();
        $this->assertTrue($target->is_active);
        $this->assertTrue($target->hasRole('viewer'));
        $this->assertSame('Renamed', $target->name);
    }

    /* ---------- attachments ---------- */

    private function fakeAttachment(): Attachment
    {
        config([
            'attachments.types.doc' => [
                'model' => null,
                'view' => 'settings.users.view',
                'update' => 'settings.users.update',
                'delete' => 'settings.users.update',
            ],
        ]);
        Storage::fake();
        Storage::put('attachments/secret.txt', 'top secret');

        return Attachment::create([
            'attachable_type' => 'doc',
            'attachable_id' => 1,
            'name' => 'secret.txt',
            'path' => 'attachments/secret.txt',
            'mime' => 'text/plain',
            'size' => 10,
        ]);
    }

    public function test_attachments_need_the_types_permissions(): void
    {
        $file = $this->fakeAttachment();

        $nobody = $this->makeUser();
        $this->actingAs($nobody)->getJson('/attachments?type=doc&id=1')->assertForbidden();
        $this->actingAs($nobody)->get("/attachments/{$file->id}")->assertForbidden();
        $this->actingAs($nobody)->deleteJson("/attachments/{$file->id}")->assertForbidden();
        $this->assertDatabaseHas('attachments', ['id' => $file->id]);

        $reader = $this->makeUser(['settings.users.view']);
        $this->actingAs($reader)->get("/attachments/{$file->id}")->assertOk();
        $this->actingAs($reader)->deleteJson("/attachments/{$file->id}")->assertForbidden();
    }

    public function test_unknown_attachment_type_is_404(): void
    {
        $this->actingAs($this->makeUser())->getJson('/attachments?type=nope&id=1')->assertNotFound();
    }

    public function test_attachments_on_a_missing_record_are_rejected(): void
    {
        $this->fakeAttachment();
        config(['attachments.types.doc.model' => User::class]);

        $user = $this->makeUser(['settings.users.view', 'settings.users.update']);

        $this->actingAs($user)->getJson('/attachments?type=doc&id=999999')->assertNotFound();

        $this->actingAs($user)->post('/attachments', [
            'type' => 'doc',
            'id' => 999999,
            'file' => UploadedFile::fake()->create('a.pdf', 10),
        ], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_only_allowed_attachment_formats_are_accepted(): void
    {
        $this->fakeAttachment();
        $user = $this->makeUser(['settings.users.update']);
        $post = fn (string $name) => $this->actingAs($user)->post('/attachments', [
            'type' => 'doc',
            'id' => 1,
            'file' => UploadedFile::fake()->create($name, 10),
        ], ['Accept' => 'application/json']);

        foreach (['malware.exe', 'page.html', 'notes.docx'] as $bad) {
            $post($bad)->assertStatus(422);
        }
        $post('data.csv')->assertCreated();
    }
}
