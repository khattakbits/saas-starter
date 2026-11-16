<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use KhattakBits\Core\CoreServiceProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * RK21 (docs/platform/11 §4.1.1, 03 D2.3): control plane = single DB, koi tenancy nahi.
 *
 * `KB_TENANCY_ENABLED=false` par plain `php artisan migrate` ko **poora** schema banana
 * chahiye — central aur tenant dono folders. Warna CP mein audit_logs, settings, email_*
 * aur attachments tables hi na bante aur boot par 500 aata.
 *
 * Yeh test ek temporary SQLite file par asli `migrate:fresh` chalata hai, phir tables ginta
 * hai. `migrate --pretend` par bharosa nahi kiya jata — woh sirf SQL print karta hai.
 */
class CentralSchemaParityTest extends TestCase
{
    /** 03 D2 inventory: 8 central + 14 tenant + 1 module = 23. */
    private const EXPECTED_TABLES = 23;

    private const CENTRAL_TABLES = [
        'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
    ];

    private const TENANT_TABLES = [
        'attachments', 'settings', 'audit_logs', 'deleted_documents', 'document_sequences',
        'document_views', 'email_accounts', 'email_logs', 'email_templates',
        'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
    ];

    private const MODULE_TABLES = ['demo_entries'];

    private ?string $database = null;

    protected function tearDown(): void
    {
        if ($this->database !== null && is_file($this->database)) {
            @unlink($this->database);
        }

        parent::tearDown();
    }

    /** @return list<string> */
    private function freshDatabaseTables(bool $tenancyEnabled): array
    {
        config(['khattakbits.tenancy.enabled' => $tenancyEnabled]);

        $this->database = tempnam(sys_get_temp_dir(), 'kb-parity-');

        config([
            'database.connections.parity' => [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('parity');

        $this->artisan('migrate:fresh', ['--database' => 'parity', '--force' => true])
            ->assertExitCode(0);

        $tables = array_map(
            static fn (array $table): string => (string) $table['name'],
            Schema::connection('parity')->getTables(),
        );

        // SQLite ke internal tables aur framework ki bookkeeping (`migrations`) schema ka hissa nahi.
        $tables = array_values(array_filter(
            $tables,
            static fn (string $name): bool => ! str_starts_with($name, 'sqlite_') && $name !== 'migrations',
        ));

        sort($tables);

        return $tables;
    }

    public function test_tenancy_off_builds_the_full_single_database_schema(): void
    {
        $tables = $this->freshDatabaseTables(false);

        $this->assertCount(
            self::EXPECTED_TABLES,
            $tables,
            'Tenancy off par poora schema banna chahiye. Mili tables: '.implode(', ', $tables),
        );

        foreach ([...self::CENTRAL_TABLES, ...self::TENANT_TABLES, ...self::MODULE_TABLES] as $table) {
            $this->assertContains($table, $tables, "{$table} missing — folder register nahi hua.");
        }
    }

    /**
     * Flag boot ke waqt parha jata hai, is liye runtime par `config()` badal kar "true"
     * wala schema test nahi kiya ja sakta (registrations pehle hi ho chuki hoti hain).
     * Flag=true ka faisla `MigrationPathsTest` aur `MigrationSplitTest` path-level par
     * verify karte hain; asli do-DB split test **P5** mein aata hai jab `stancl/tenancy`
     * install hota hai (11 §4.3).
     *
     * Jo cheez abhi test honi chahiye: flag true + package missing = loud failure, warna
     * tenant migrations khamoshi se gayab ho jatin.
     */
    public function test_tenancy_on_without_the_tenancy_package_fails_loudly(): void
    {
        config(['khattakbits.tenancy.enabled' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stancl/tenancy is not installed');

        (new CoreServiceProvider($this->app))->boot();
    }

    public function test_flag_defaults_to_off_so_the_control_plane_migrates_everything(): void
    {
        // .env / config default: KB_TENANCY_ENABLED absent => false.
        $this->assertFalse(
            (bool) config('khattakbits.tenancy.enabled', false),
            'Default off hona chahiye, warna CP ka schema adhoora reh jata hai (RK21).',
        );
    }
}
