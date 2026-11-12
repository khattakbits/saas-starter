<?php

namespace Tests\Feature;

use App\Core\Models\Attachment;
use App\Models\User;
use App\Modules\Examples\Models\DemoEntry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use KhattakBits\Core\Support\Naming;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * B-1 regression: attachment id enumerate kar ke doosre record ki file
 * download ya delete nahi ho sakni chahiye.
 */
class AttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);

        Storage::fake();
        Storage::put('attachments/secret.pdf', 'top secret');
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

    /** Owner wo user hai jo record banata hai (`created_by` IsDocument se set hota hai). */
    private function entryOwnedBy(User $owner): DemoEntry
    {
        $this->actingAs($owner);

        $entry = new DemoEntry(['party' => 'Acme', 'amount' => 100, 'posting_date' => '2026-10-29']);
        $entry->name = Naming::next('DEMO');
        $entry->save();

        return $entry->fresh();
    }

    private function attachmentFor(DemoEntry $entry, ?User $uploader = null): Attachment
    {
        return Attachment::create([
            'attachable_type' => 'demo_entry',
            'attachable_id' => $entry->id,
            'name' => 'secret.pdf',
            'path' => 'attachments/secret.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by' => $uploader?->id,
        ]);
    }

    public function test_intruder_cannot_download_another_users_attachment(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $file = $this->attachmentFor($this->entryOwnedBy($owner), $owner);

        $intruder = $this->makeUser(['examples.entries.view']);

        $this->actingAs($intruder)
            ->get("/attachments/{$file->id}")
            ->assertForbidden();
    }

    public function test_intruder_cannot_delete_another_users_attachment(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $file = $this->attachmentFor($this->entryOwnedBy($owner), $owner);

        $intruder = $this->makeUser(['examples.entries.view']);

        $this->actingAs($intruder)
            ->deleteJson("/attachments/{$file->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $file->id]);
        Storage::assertExists('attachments/secret.pdf');
    }

    public function test_intruder_cannot_list_another_users_record_attachments(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $entry = $this->entryOwnedBy($owner);
        $this->attachmentFor($entry, $owner);

        $intruder = $this->makeUser(['examples.entries.view']);

        $this->actingAs($intruder)
            ->getJson("/attachments?type=demo_entry&id={$entry->id}")
            ->assertForbidden();
    }

    public function test_owner_can_download_own_attachment(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $file = $this->attachmentFor($this->entryOwnedBy($owner), $owner);

        $this->actingAs($owner)->get("/attachments/{$file->id}")->assertOk();
    }

    public function test_owner_with_delete_permission_can_delete_own_attachment(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create', 'examples.entries.update']);
        $file = $this->attachmentFor($this->entryOwnedBy($owner), $owner);

        $this->actingAs($owner)->deleteJson("/attachments/{$file->id}")->assertNoContent();

        $this->assertDatabaseMissing('attachments', ['id' => $file->id]);
        Storage::assertMissing('attachments/secret.pdf');
    }

    public function test_manager_can_read_and_delete_another_users_attachment(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $file = $this->attachmentFor($this->entryOwnedBy($owner), $owner);

        $manager = $this->makeUser(['examples.entries.view', 'examples.entries.update']);

        $this->actingAs($manager)->get("/attachments/{$file->id}")->assertOk();
        $this->actingAs($manager)->deleteJson("/attachments/{$file->id}")->assertNoContent();
    }

    public function test_attachment_whose_parent_record_is_gone_is_404(): void
    {
        $user = $this->makeUser(['examples.entries.view', 'examples.entries.update']);

        $orphan = Attachment::create([
            'attachable_type' => 'demo_entry',
            'attachable_id' => 999999,
            'name' => 'secret.pdf',
            'path' => 'attachments/secret.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
        ]);

        $this->actingAs($user)->get("/attachments/{$orphan->id}")->assertNotFound();
        $this->actingAs($user)->deleteJson("/attachments/{$orphan->id}")->assertNotFound();
        $this->assertDatabaseHas('attachments', ['id' => $orphan->id]);
    }

    public function test_authorize_callback_overrides_the_scope_rule(): void
    {
        $owner = $this->makeUser(['examples.entries.view', 'examples.entries.create']);
        $entry = $this->entryOwnedBy($owner);
        $file = $this->attachmentFor($entry, $owner);

        config([
            'attachments.types.demo_entry.authorize' => fn (User $user, DemoEntry $record): bool => false,
        ]);

        // Owner hone ke bawajood callback ne mana kar diya
        $this->actingAs($owner)->get("/attachments/{$file->id}")->assertForbidden();
    }
}
