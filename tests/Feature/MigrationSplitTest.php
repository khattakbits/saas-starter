<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use KhattakBits\Core\CoreServiceProvider;
use KhattakBits\Core\Support\MigrationPaths;
use KhattakBits\Core\Support\ModuleRegistry;
use ReflectionClass;
use Tests\TestCase;

/**
 * D2 (docs/platform/03 D2.3, 07 §2.2): migrations ka central/tenant split.
 *
 * Yeh test split ke teen contracts lock karta hai:
 *  1. Har folder mein exactly wohi files hain jo inventory kehti hai (na koi flat file bachi).
 *  2. Tenant folder ki koi table `users` par FK nahi rakhti (D1 ka cross-DB rule —
 *     multi-DB tenancy mein `users` central DB mein hoti hai, is liye FK impossible hai).
 *  3. Module migrations `Database/Migrations/{central,tenant}` convention follow karti hain
 *     (07 §7.1/§7.2) aur sirf tenant context mein aati hain.
 *
 * File names core package ke andar se aate hain, is liye core folder par exact names ki
 * jagah counts + naming pattern check hoti hai — warna host ka test core ke har rename par
 * toot jata (version skew ka asar CI par dikhna chahiye, is test par nahi).
 */
class MigrationSplitTest extends TestCase
{
    use RefreshDatabase;

    /** Host ki apni migrations — yeh repo in files ka malik hai, is liye exact names. */
    private const HOST_CENTRAL = [
        '0001_01_01_000001_create_cache_table.php',
        '0001_01_01_000002_create_jobs_table.php',
    ];

    private const HOST_TENANT = [
        '2026_10_26_101855_create_permission_tables.php',
    ];

    private function coreMigrations(): string
    {
        // src/CoreServiceProvider.php -> package root
        $root = dirname((new ReflectionClass(CoreServiceProvider::class))->getFileName(), 2);

        return $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
    }

    /** @return list<string> */
    private function filesIn(string $folder): array
    {
        $files = glob($folder.DIRECTORY_SEPARATOR.'*.php') ?: [];
        $names = array_map(static fn (string $f): string => basename($f), $files);
        sort($names);

        return array_values($names);
    }

    /**
     * Windows par `database_path('a/b')` mixed separators deta hai (`...\a/b`) aur
     * `ModuleRegistry` bhi `'/'` se join karta hai — compare karne se pehle normalize.
     */
    private function normalize(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param  array<int|string, string>  $paths
     * @return array<int|string, string>
     */
    private function normalizeAll(array $paths): array
    {
        return array_map(fn (string $p): string => $this->normalize($p), $paths);
    }

    /* ---------- (1) folder contents ---------- */

    public function test_host_central_folder_holds_exactly_the_inventory_files(): void
    {
        $this->assertSame(self::HOST_CENTRAL, $this->filesIn(database_path('migrations/central')));
    }

    public function test_host_tenant_folder_holds_exactly_the_inventory_files(): void
    {
        $this->assertSame(self::HOST_TENANT, $this->filesIn(database_path('migrations/tenant')));
    }

    public function test_no_host_migration_is_left_flat_in_the_migrations_root(): void
    {
        $this->assertSame(
            [],
            $this->filesIn(database_path('migrations')),
            'Split ke baad root mein koi .php file nahi bachni chahiye — Laravel sirf root glob karta hai.',
        );
    }

    public function test_core_package_has_no_flat_migrations_left(): void
    {
        $root = $this->coreMigrations();

        $this->assertSame([], $this->filesIn($root), 'Core package ka migrations root khali hona chahiye.');

        // 03 D2 inventory: 2 central + 10 tenant files.
        $this->assertCount(2, $this->filesIn($root.'/central'), 'Core central folder mein exactly 2 files.');
        $this->assertCount(10, $this->filesIn($root.'/tenant'), 'Core tenant folder mein exactly 10 files.');
    }

    public function test_moved_files_kept_their_timestamp_prefixes(): void
    {
        $folders = [
            $this->coreMigrations().'/central',
            $this->coreMigrations().'/tenant',
            database_path('migrations/central'),
            database_path('migrations/tenant'),
        ];

        foreach ($folders as $folder) {
            foreach ($this->filesIn($folder) as $file) {
                $this->assertMatchesRegularExpression(
                    '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/',
                    $file,
                    "{$file}: rename nahi hona chahiye tha — timestamp prefix hi migration order hai.",
                );
            }
        }
    }

    public function test_module_migration_lives_in_the_tenant_folder(): void
    {
        $dir = app_path('Modules/Examples/Database/Migrations');

        $this->assertSame(
            ['2026_10_29_100000_create_demo_entries_table.php'],
            $this->filesIn($dir.'/tenant'),
        );
        $this->assertDirectoryDoesNotExist($dir.'/central', 'Examples module ki koi central migration nahi hai.');
        $this->assertSame([], $this->filesIn($dir), 'Module root mein flat migration nahi bachni chahiye.');
    }

    /* ---------- (2) tenant tables must not point at users ---------- */

    /** @return list<string> */
    private function tenantTables(): array
    {
        $folders = [
            $this->coreMigrations().'/tenant',
            database_path('migrations/tenant'),
            app_path('Modules/Examples/Database/Migrations/tenant'),
        ];

        $tables = [];

        foreach ($folders as $folder) {
            foreach ($this->filesIn($folder) as $file) {
                $sql = (string) file_get_contents($folder.DIRECTORY_SEPARATOR.$file);
                preg_match_all('/Schema::create\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $sql, $m);
                $tables = [...$tables, ...$m[1]];
            }
        }

        // Permission migration table names config se leti hai, literal nahi.
        $tables = [...$tables, ...array_values((array) config('permission.table_names'))];

        $tables = array_values(array_unique(array_filter($tables, 'is_string')));
        sort($tables);

        return $tables;
    }

    public function test_every_tenant_table_actually_exists_after_migrate(): void
    {
        $tables = $this->tenantTables();

        $this->assertNotEmpty($tables, 'Tenant folders se koi table nahi mili — split ya paths galat hain.');

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "{$table} tenant folder mein declare hai magar schema mein nahi — loadMigrationsFrom miss hai?",
            );
        }
    }

    public function test_no_tenant_table_has_a_foreign_key_to_users(): void
    {
        foreach ($this->tenantTables() as $table) {
            $foreignTables = array_map(
                static fn (array $fk): string => (string) $fk['foreign_table'],
                Schema::getForeignKeys($table),
            );

            $this->assertNotContains(
                'users',
                $foreignTables,
                "{$table} tenant folder mein hai aur `users` par FK rakhti hai — multi-DB tenancy mein `users` central hoti hai (D1 rule).",
            );
        }
    }

    /* ---------- (3) module registry ---------- */

    public function test_module_registry_resolves_only_the_tenant_folder_for_examples(): void
    {
        $registry = app(ModuleRegistry::class);

        $this->assertSame([], $registry->migrationPaths('central'));
        $this->assertSame(
            ['examples' => $this->normalize(app_path('Modules/Examples/Database/Migrations/tenant'))],
            $this->normalizeAll($registry->migrationPaths('tenant')),
        );
    }

    public function test_module_registry_rejects_an_unknown_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ModuleRegistry::class)->migrationPaths('shared');
    }

    public function test_resolved_folders_include_core_host_and_module_paths(): void
    {
        $folders = MigrationPaths::folders(database_path('migrations'), app(ModuleRegistry::class));

        $central = $this->normalizeAll($folders['central']);
        $tenant = $this->normalizeAll($folders['tenant']);

        $this->assertContains($this->normalize(database_path('migrations').'/central'), $central);
        $this->assertContains($this->normalize(database_path('migrations').'/tenant'), $tenant);
        $this->assertContains(
            $this->normalize(app_path('Modules/Examples/Database/Migrations/tenant')),
            $tenant,
            'Module tenant path resolve hona chahiye (07 §7.2).',
        );

        // Tenancy off: dono register hote hain; on: sirf central.
        $off = $this->normalizeAll(MigrationPaths::forMigrate($folders, false));
        $this->assertContains($this->normalize(database_path('migrations').'/tenant'), $off);

        $on = $this->normalizeAll(MigrationPaths::forMigrate($folders, true));
        $this->assertNotContains($this->normalize(database_path('migrations').'/tenant'), $on);
        $this->assertSame($central, $on);
    }
}
