<?php

namespace Tests\Feature;

use App\Core\Models\AuditLog;
use App\Core\Models\DeletedDocument;
use App\Core\Support\Naming;
use App\Models\User;
use App\Modules\Examples\Models\DemoEntry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentActivityTest extends TestCase
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

    private function activity(DemoEntry $e, User $user): array
    {
        return $this->actingAs($user)->getJson("/documents/demo_entry/{$e->id}/activity")->assertOk()->json();
    }

    public function test_a_view_within_the_window_counts_once_and_after_it_counts_again(): void
    {
        $e = $this->entry();
        $user = $this->admin();
        $url = "/documents/demo_entry/{$e->id}/views";

        $this->travelTo(Carbon::parse('2026-10-29 10:00:00'));

        $this->actingAs($user)->post($url)->assertNoContent();
        $this->actingAs($user)->post($url)->assertNoContent();   // window ke andar

        $this->assertDatabaseCount('document_views', 1);
        $this->assertDatabaseHas('document_views', [
            'doc_type' => 'demo_entry',
            'doc_id' => $e->id,
            'user_id' => $user->id,
            'views' => 1,
        ]);

        $this->travel(11)->minutes();   // default dedupe window 10 minutes
        $this->actingAs($user)->post($url)->assertNoContent();

        $this->assertDatabaseCount('document_views', 1);
        $this->assertDatabaseHas('document_views', [
            'doc_type' => 'demo_entry',
            'doc_id' => $e->id,
            'user_id' => $user->id,
            'views' => 2,
        ]);
    }

    public function test_two_users_are_counted_separately(): void
    {
        $e = $this->entry();
        $one = $this->admin();
        $two = $this->makeUser(['examples.entries.view']);
        $url = "/documents/demo_entry/{$e->id}/views";

        $this->travelTo(Carbon::parse('2026-10-29 10:00:00'));
        $this->actingAs($two)->post($url)->assertNoContent();

        $this->travel(11)->minutes();
        $this->actingAs($one)->post($url)->assertNoContent();

        $this->travel(11)->minutes();
        $this->actingAs($one)->post($url)->assertNoContent();   // window ke baad: dobara gina

        $data = $this->activity($e, $one);

        $this->assertSame(3, $data['views']['total']);
        $this->assertSame(2, $data['views']['people']);
        $this->assertCount(2, $data['views']['viewers']);

        // Newest first
        $this->assertSame($one->id, $data['views']['viewers'][0]['user_id']);
        $this->assertSame(2, $data['views']['viewers'][0]['views']);
        $this->assertSame($two->id, $data['views']['viewers'][1]['user_id']);
        $this->assertSame(1, $data['views']['viewers'][1]['views']);
    }

    public function test_activity_needs_the_view_permission(): void
    {
        $e = $this->entry();
        $nobody = $this->makeUser();

        $this->actingAs($nobody)->getJson("/documents/demo_entry/{$e->id}/activity")->assertForbidden();
        $this->actingAs($nobody)->post("/documents/demo_entry/{$e->id}/views")->assertForbidden();

        $this->assertDatabaseCount('document_views', 0);
    }

    public function test_unknown_type_and_missing_document_are_not_found(): void
    {
        $user = $this->admin();
        $e = $this->entry();

        $this->actingAs($user)->getJson('/documents/unknown_doc/1/activity')->assertNotFound();
        $this->actingAs($user)->post('/documents/unknown_doc/1/views')->assertNotFound();

        $this->actingAs($user)->getJson('/documents/demo_entry/999999/activity')->assertNotFound();
        $this->actingAs($user)->post('/documents/demo_entry/999999/views')->assertNotFound();
    }

    public function test_a_soft_deleted_document_is_not_found(): void
    {
        $e = $this->entry();
        $e->delete();

        $this->actingAs($this->admin())->getJson("/documents/demo_entry/{$e->id}/activity")->assertNotFound();
    }

    public function test_the_timeline_is_newest_first(): void
    {
        $e = $this->entry();
        $e->update(['party' => 'Beta']);
        $e->submitDoc();
        $e->cancelDoc();

        $timeline = $this->activity($e, $this->admin())['timeline'];

        $ids = array_column($timeline, 'id');
        $descending = $ids;
        rsort($descending);
        $this->assertSame($descending, $ids);

        $this->assertSame(
            ['Cancelled', 'Submitted', 'Edited', 'Created as Draft'],
            array_column($timeline, 'title'),
        );

        // ISO8601 UTC
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $timeline[0]['at']);
    }

    public function test_older_entries_are_paged_with_before(): void
    {
        $e = $this->entry();
        $user = $this->admin();

        for ($i = 0; $i < 54; $i++) {   // created + 54 = 55 entries
            AuditLog::create([
                'event' => 'doc.updated',
                'subject_type' => 'demo_entry',
                'subject_id' => $e->id,
                'subject_label' => $e->name,
                'meta' => ['changes' => ['party' => ['from' => 'a', 'to' => 'b']]],
                'created_at' => now(),
            ]);
        }

        $first = $this->actingAs($user)->getJson("/documents/demo_entry/{$e->id}/activity")->json();

        $this->assertCount(50, $first['timeline']);
        $this->assertSame($first['timeline'][49]['id'], $first['next_before']);

        $second = $this->actingAs($user)
            ->getJson("/documents/demo_entry/{$e->id}/activity?before={$first['next_before']}")
            ->json();

        $this->assertCount(5, $second['timeline']);
        $this->assertNull($second['next_before']);
        $this->assertLessThan($first['next_before'], $second['timeline'][0]['id']);
    }

    public function test_the_full_lifecycle_has_exactly_one_entry_per_step(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $this->post('/examples/entries', ['party' => 'Acme', 'amount' => 100, 'posting_date' => '2026-10-29'])->assertRedirect();
        $e = DemoEntry::sole();

        $this->put("/examples/entries/{$e->id}", ['party' => 'Acme Ltd', 'amount' => 150, 'posting_date' => '2026-10-29'])->assertRedirect();
        $this->post("/examples/entries/{$e->id}/submit")->assertRedirect();
        $this->post("/examples/entries/{$e->id}/cancel")->assertRedirect();
        $this->post("/examples/entries/{$e->id}/amend")->assertRedirect();
        $this->delete("/examples/entries/{$e->id}")->assertRedirect();
        $this->post('/settings/recycle-bin/restore', ['ids' => [DeletedDocument::firstOrFail()->id]])->assertRedirect();

        $copy = DemoEntry::query()->where('amended_from', $e->id)->firstOrFail();

        $timeline = $this->activity($e, $user)['timeline'];

        $this->assertSame([
            'doc.restored',
            'doc.deleted',
            'doc.amended',
            'doc.cancelled',
            'doc.submitted',
            'doc.updated',
            'doc.created',
        ], array_column($timeline, 'event'));

        $this->assertSame([
            'Restored as Draft',
            'Moved to Recycle Bin',
            'Amended to '.$copy->name,
            'Cancelled',
            'Submitted',
            'Edited',
            'Created as Draft',
        ], array_column($timeline, 'title'));

        foreach ($timeline as $row) {
            $this->assertSame($user->id, $row['user_id']);
            $this->assertSame($user->name, $row['user_name']);
        }

        $this->assertSame(1, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.created')->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.updated')->count());

        // Copy par sirf apni created entry hai, purane document par amended
        $copyTimeline = $this->actingAs($user)->getJson("/documents/demo_entry/{$copy->id}/activity")->json('timeline');
        $this->assertSame(['doc.created'], array_column($copyTimeline, 'event'));
        $this->assertSame('Created by amending '.$e->name, $copyTimeline[0]['title']);
    }

    public function test_a_transition_does_not_log_an_edit(): void
    {
        $e = $this->entry();

        $e->submitDoc();
        $e->cancelDoc();

        $this->assertSame(0, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.updated')->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.submitted')->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.cancelled')->count());

        $timeline = $this->activity($e, $this->admin())['timeline'];
        $this->assertSame(['doc.cancelled', 'doc.submitted', 'doc.created'], array_column($timeline, 'event'));
    }

    public function test_restoring_as_draft_does_not_log_an_edit(): void
    {
        $e = $this->entry();
        $e->delete();
        $e->restoreAsDraft();

        $this->assertSame(0, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.updated')->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.restored')->count());
    }

    public function test_a_save_without_real_changes_logs_nothing(): void
    {
        $e = $this->entry();
        $e->update(['party' => 'Acme']);   // same value
        $e->touch();                       // sirf updated_at

        $this->assertSame(0, AuditLog::query()->where('subject_id', $e->id)->where('event', 'doc.updated')->count());
    }

    public function test_a_timeline_never_mixes_documents_or_types(): void
    {
        $a = $this->entry();
        $b = $this->entry();
        $a->update(['party' => 'A changed']);
        $b->update(['party' => 'B changed']);

        // Doosre type ka row, wahi subject_id
        $foreign = AuditLog::create([
            'event' => 'doc.created',
            'subject_type' => 'another_doc',
            'subject_id' => $a->id,
            'subject_label' => 'Something else',
            'created_at' => now(),
        ]);

        $timeline = $this->activity($a, $this->admin())['timeline'];
        $this->assertSame(['doc.updated', 'doc.created'], array_column($timeline, 'event'));

        $bIds = AuditLog::query()->where('subject_id', $b->id)->pluck('id')->all();
        foreach ($timeline as $row) {
            $this->assertNotContains($row['id'], $bIds);
            $this->assertNotSame($foreign->id, $row['id']);
        }
    }

    public function test_the_audit_log_show_page_still_renders_an_edit(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $e = $this->entry();
        $this->put("/examples/entries/{$e->id}", [
            'party' => 'Beta',
            'amount' => 250,
            'posting_date' => '2026-10-30',
        ])->assertRedirect();

        $log = AuditLog::query()->where('event', 'doc.updated')->sole();

        $this->assertSame(['party', 'amount', 'posting_date'], array_keys($log->meta['changes']));

        $this->get("/settings/audit-log/{$log->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/AuditLog/Show')
                ->where('log.changes.party.from', 'Acme')
                ->where('log.changes.party.to', 'Beta')
                ->where('log.changes.amount.from', '100.00')
                ->where('log.changes.amount.to', '250.00')
                ->where('log.changes.posting_date.from', '2026-10-29')
                ->where('log.changes.posting_date.to', '2026-10-30'));
    }

    public function test_the_form_payload_exposes_the_updated_timestamp_for_the_activity_key(): void
    {
        $e = $this->entry();

        $this->actingAs($this->admin())
            ->get("/examples/entries/{$e->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Examples/Entries/Form')
                ->where('entry.docstatus', 0)
                ->where('entry.updated_at', $e->updated_at->toIso8601String()));
    }

    public function test_activity_details_list_changed_fields_only(): void
    {
        $e = $this->entry();
        $e->update(['party' => 'Beta', 'amount' => 300]);

        $timeline = $this->activity($e, $this->admin())['timeline'];

        $this->assertSame('Edited', $timeline[0]['title']);
        $this->assertSame(['party', 'amount'], $timeline[0]['details']);
        $this->assertNull($timeline[1]['details']);   // doc.created
    }
}
