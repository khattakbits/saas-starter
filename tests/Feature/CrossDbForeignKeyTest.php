<?php

namespace Tests\Feature;

use App\Core\Models\Attachment;
use App\Core\Models\AuditLog;
use App\Core\Support\Audit;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use KhattakBits\Core\Models\EmailLog;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Step 0 (docs/platform/03-EXTRACTION-PLAN.md): users are central, so the five
 * referencing tables must not carry a cross-DB FK. Yeh test us contract ko lock
 * karta hai aur prove karta hai ke dangling user ids se kuch nahi tootta.
 */
class CrossDbForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    /** Tables jo user ko reference karti hain (columns: uploaded_by / user_id / deleted_by). */
    private const REFERENCING_TABLES = [
        'attachments',
        'audit_logs',
        'deleted_documents',
        'document_views',
        'email_logs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(PermissionSeeder::class);
    }

    private function makeUser(array $permissions = []): User
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

        return $user->fresh();
    }

    /* ---------- (a) schema contract ---------- */

    public function test_no_foreign_key_points_to_users_on_referencing_tables(): void
    {
        foreach (self::REFERENCING_TABLES as $table) {
            $foreignTables = array_map(
                fn (array $fk): string => (string) $fk['foreign_table'],
                Schema::getForeignKeys($table),
            );

            $this->assertNotContains(
                'users',
                $foreignTables,
                "{$table} still has a foreign key to users (cross-DB FK is impossible in multi-DB tenancy).",
            );
        }
    }

    public function test_referencing_columns_exist_and_are_nullable(): void
    {
        $this->assertTrue(Schema::hasColumns('attachments', ['uploaded_by']));
        $this->assertTrue(Schema::hasColumns('audit_logs', ['user_id', 'user_name', 'user_email']));
        $this->assertTrue(Schema::hasColumns('deleted_documents', ['deleted_by', 'deleted_by_name']));
        $this->assertTrue(Schema::hasColumns('document_views', ['user_id', 'user_name']));
        $this->assertTrue(Schema::hasColumns('email_logs', ['user_id']));
    }

    /* ---------- (b) dangling user ids still render ---------- */

    public function test_audit_list_renders_rows_whose_user_no_longer_exists(): void
    {
        AuditLog::create([
            'user_id' => 999999,
            'user_name' => 'Ghost User',
            'user_email' => 'ghost@example.com',
            'event' => 'doc.created',
            'subject_label' => 'DEMO-00001',
            'created_at' => now(),
        ]);

        $this->actingAs($this->makeUser(['settings.audit.view']))
            ->getJson('/settings/audit-log/data')
            ->assertOk()
            ->assertJsonPath('data.0.user', 'Ghost User')
            ->assertJsonPath('data.0.user_email', 'ghost@example.com');
    }

    public function test_attachment_with_a_dangling_uploader_still_downloads(): void
    {
        config([
            'attachments.types.doc' => ['model' => null, 'view' => null, 'update' => null, 'delete' => null],
        ]);

        Storage::fake();
        Storage::put('attachments/secret.pdf', 'top secret');

        $file = Attachment::create([
            'attachable_type' => 'doc',
            'attachable_id' => 1,
            'name' => 'secret.pdf',
            'path' => 'attachments/secret.pdf',
            'mime' => 'application/pdf',
            'size' => 10,
            'uploaded_by' => 999999,
        ]);

        $this->actingAs($this->makeUser())
            ->get("/attachments/{$file->id}")
            ->assertOk();
    }

    public function test_email_log_list_renders_rows_whose_user_no_longer_exists(): void
    {
        EmailLog::create([
            'to_email' => 'someone@example.com',
            'subject' => 'Welcome',
            'status' => 'sent',
            'attempts' => 1,
            'user_id' => 999999,
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        $this->actingAs($this->makeUser(['settings.email_log.view']))
            ->getJson('/settings/email-log/data')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /* ---------- (c) snapshot columns ---------- */

    public function test_audit_writes_name_and_email_snapshots(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);
        Audit::log('test.snapshot');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'event' => 'test.snapshot',
        ]);
    }

    public function test_audit_snapshot_survives_when_there_is_no_authenticated_user(): void
    {
        Audit::log('test.anonymous');

        $row = AuditLog::query()->where('event', 'test.anonymous')->firstOrFail();

        $this->assertNull($row->user_id);
        $this->assertNull($row->user_name);
        $this->assertNull($row->user_email);
    }

    /* ---------- explicit null-on-delete handling ---------- */

    public function test_deleting_a_user_detaches_references_but_keeps_snapshots(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);
        Audit::log('doc.created', null, 'DEMO-00001');

        $attachment = Attachment::create([
            'attachable_type' => 'doc',
            'attachable_id' => 1,
            'name' => 'a.pdf',
            'path' => 'attachments/a.pdf',
            'size' => 3,
            'uploaded_by' => $user->id,
        ]);

        DB::table('deleted_documents')->insert([
            'doc_type' => 'demo_entry',
            'doc_id' => 1,
            'title' => 'DEMO-00001',
            'deleted_by' => $user->id,
            'deleted_by_name' => $user->name,
            'original_docstatus' => 0,
            'created_at' => now(),
        ]);

        DB::table('document_views')->insert([
            'doc_type' => 'demo_entry',
            'doc_id' => 1,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'views' => 1,
            'first_viewed_at' => now(),
            'last_viewed_at' => now(),
        ]);

        $emailLog = EmailLog::create([
            'to_email' => $user->email,
            'subject' => 'Welcome',
            'status' => 'sent',
            'attempts' => 1,
            'user_id' => $user->id,
            'queued_at' => now(),
        ]);

        $user->delete();

        $this->assertNull($attachment->fresh()->uploaded_by);
        $this->assertNull($emailLog->fresh()->user_id);
        $this->assertNull(DB::table('deleted_documents')->where('doc_id', 1)->value('deleted_by'));
        $this->assertNull(DB::table('document_views')->where('doc_id', 1)->value('user_id'));

        // Snapshot columns zinda rehte hain — audit/recycle bin readable rahe
        $log = AuditLog::query()->where('event', 'doc.created')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame($user->name, $log->user_name);
        $this->assertSame($user->email, $log->user_email);
        $this->assertSame($user->name, DB::table('deleted_documents')->where('doc_id', 1)->value('deleted_by_name'));
    }
}
