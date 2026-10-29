<?php

namespace Tests\Feature;

use App\Core\Exceptions\DocumentException;
use App\Core\Models\Attachment;
use App\Core\Models\DeletedDocument;
use App\Core\Support\Naming;
use App\Core\Support\SettingsStore;
use App\Models\User;
use App\Modules\Examples\Models\DemoEntry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentTest extends TestCase
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

    /** 0 = Draft, 1 = Submitted, 2 = Cancelled */
    private function entry(int $status = 0, array $attrs = []): DemoEntry
    {
        $e = new DemoEntry($attrs + ['party' => 'Acme', 'amount' => 100, 'posting_date' => '2026-10-29']);
        $e->name = Naming::next('DEMO');
        $e->save();

        if ($status >= 1) {
            $e->submitDoc();
        }
        if ($status === 2) {
            $e->cancelDoc();
        }

        return $e->fresh();
    }

    public function test_submitting_locks_the_document(): void
    {
        $e = $this->entry();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/examples/entries/{$e->id}/submit")->assertRedirect();
        $this->assertSame(1, $e->fresh()->docstatus);

        $this->actingAs($admin)
            ->put("/examples/entries/{$e->id}", ['party' => 'Hacked', 'amount' => 5, 'posting_date' => '2026-10-30'])
            ->assertSessionHas('error');
        $this->assertSame('Acme', $e->fresh()->party);
    }

    public function test_model_refuses_direct_edits_of_a_submitted_document(): void
    {
        $e = $this->entry(1);

        $this->expectException(DocumentException::class);
        $e->update(['party' => 'Changed']);
    }

    public function test_a_zero_amount_cannot_be_submitted(): void
    {
        $e = $this->entry(0, ['amount' => 0]);

        $this->actingAs($this->admin())->post("/examples/entries/{$e->id}/submit")->assertSessionHas('error');
        $this->assertSame(0, $e->fresh()->docstatus);
    }

    public function test_a_submitted_document_cannot_be_deleted(): void
    {
        $e = $this->entry(1);

        $this->actingAs($this->admin())->delete("/examples/entries/{$e->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted('demo_entries', ['id' => $e->id]);
    }

    public function test_deleted_draft_goes_to_the_bin_and_restores_as_draft(): void
    {
        $e = $this->entry();
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/examples/entries/{$e->id}")->assertRedirect('/examples/entries');
        $this->assertSoftDeleted('demo_entries', ['id' => $e->id]);
        $this->assertDatabaseHas('deleted_documents', ['doc_type' => 'demo_entry', 'doc_id' => $e->id]);

        $bin = DeletedDocument::firstOrFail();
        $this->actingAs($admin)->post('/settings/recycle-bin/restore', ['ids' => [$bin->id]])->assertRedirect();

        $restored = DemoEntry::find($e->id);
        $this->assertNotNull($restored);
        $this->assertSame(0, $restored->docstatus);
        $this->assertDatabaseMissing('deleted_documents', ['doc_id' => $e->id]);
    }

    public function test_a_cancelled_document_restores_as_draft(): void
    {
        $e = $this->entry(2);
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/examples/entries/{$e->id}")->assertRedirect();
        $this->actingAs($admin)->post('/settings/recycle-bin/restore', ['ids' => [DeletedDocument::firstOrFail()->id]]);

        $restored = DemoEntry::findOrFail($e->id);
        $this->assertSame(0, $restored->docstatus);
        $this->assertNull($restored->cancelled_at);
        $this->assertNull($restored->submitted_at);
    }

    public function test_amend_creates_one_linked_draft(): void
    {
        $e = $this->entry(2);

        $copy = $e->amend();
        $this->assertSame($e->name.'-1', $copy->name);
        $this->assertSame(0, $copy->docstatus);
        $this->assertSame($e->id, $copy->amended_from);

        $this->expectException(DocumentException::class);
        $e->amend();
    }

    public function test_only_a_cancelled_document_can_be_amended(): void
    {
        $this->expectException(DocumentException::class);
        $this->entry(1)->amend();
    }

    public function test_actions_need_their_own_permissions(): void
    {
        $e = $this->entry();
        $viewer = $this->makeUser(['examples.entries.view']);

        $this->actingAs($viewer)->post("/examples/entries/{$e->id}/submit")->assertForbidden();
        $this->actingAs($viewer)->delete("/examples/entries/{$e->id}")->assertForbidden();
        $this->assertSame(0, $e->fresh()->docstatus);
    }

    public function test_restoring_needs_the_documents_own_delete_permission(): void
    {
        $e = $this->entry();
        $e->delete();

        $user = $this->makeUser(['settings.recycle_bin.view', 'settings.recycle_bin.restore']);
        $this->actingAs($user)
            ->post('/settings/recycle-bin/restore', ['ids' => [DeletedDocument::firstOrFail()->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('demo_entries', ['id' => $e->id]);
    }

    public function test_purging_removes_the_document_and_its_files(): void
    {
        Storage::fake();
        Storage::put('attachments/doc.pdf', 'pdf');

        $e = $this->entry();
        Attachment::create([
            'attachable_type' => 'demo_entry',
            'attachable_id' => $e->id,
            'name' => 'doc.pdf',
            'path' => 'attachments/doc.pdf',
            'mime' => 'application/pdf',
            'size' => 3,
        ]);

        $e->delete();
        Storage::assertExists('attachments/doc.pdf');   // soft delete: files rehti hain

        $this->actingAs($this->admin())
            ->post('/settings/recycle-bin/purge', ['ids' => [DeletedDocument::firstOrFail()->id]])
            ->assertRedirect();

        Storage::assertMissing('attachments/doc.pdf');
        $this->assertDatabaseMissing('attachments', ['attachable_id' => $e->id]);
        $this->assertNull(DemoEntry::withTrashed()->find($e->id));
        $this->assertDatabaseCount('deleted_documents', 0);
    }

    public function test_bulk_delete_skips_submitted_documents(): void
    {
        [$draft, $submitted, $cancelled] = [$this->entry(0), $this->entry(1), $this->entry(2)];

        $this->actingAs($this->admin())
            ->post('/examples/entries/bulk-delete', ['ids' => [$draft->id, $submitted->id, $cancelled->id]])
            ->assertRedirect();

        $this->assertSoftDeleted('demo_entries', ['id' => $draft->id]);
        $this->assertSoftDeleted('demo_entries', ['id' => $cancelled->id]);
        $this->assertNotSoftDeleted('demo_entries', ['id' => $submitted->id]);
    }

    public function test_the_bin_empties_itself_after_the_retention_period(): void
    {
        $old = $this->entry();
        $fresh = $this->entry();
        $old->delete();
        $fresh->delete();
        DeletedDocument::query()->where('doc_id', $old->id)->update(['created_at' => now()->subDays(40)]);

        $this->artisan('bin:purge')->assertSuccessful();

        $this->assertNull(DemoEntry::withTrashed()->find($old->id));
        $this->assertNotNull(DemoEntry::withTrashed()->find($fresh->id));
    }

    public function test_retention_zero_keeps_everything(): void
    {
        $e = $this->entry();
        $e->delete();
        DeletedDocument::query()->update(['created_at' => now()->subDays(400)]);
        app(SettingsStore::class)->setMany(['recycle_bin_days' => 0]);

        $this->artisan('bin:purge')->assertSuccessful();

        $this->assertNotNull(DemoEntry::withTrashed()->find($e->id));
    }
}
