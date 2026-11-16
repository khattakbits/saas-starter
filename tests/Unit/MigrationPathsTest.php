<?php

namespace Tests\Unit;

use KhattakBits\Core\Support\MigrationPaths;
use PHPUnit\Framework\TestCase;

/**
 * D2 (docs/platform/03 D2.3): path resolution ka pure logic.
 *
 * Yeh test app boot nahi karta — `MigrationPaths` sirf filesystem dekhta hai, is liye
 * flag ke dono values ke liye faisla yahan deterministic tarike se verify hota hai.
 * Asli schema (`migrate:fresh`) `CentralSchemaParityTest` mein check hota hai.
 */
class MigrationPathsTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kb-mp-'.bin2hex(random_bytes(6));

        mkdir($this->base.'/central', 0777, true);
        mkdir($this->base.'/tenant', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->base.'/central', $this->base.'/tenant', $this->base] as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        parent::tearDown();
    }

    public function test_folders_returns_both_subfolders_when_they_exist(): void
    {
        $folders = MigrationPaths::folders($this->base);

        $this->assertSame([$this->base.DIRECTORY_SEPARATOR.'central'], $folders['central']);
        $this->assertSame([$this->base.DIRECTORY_SEPARATOR.'tenant'], $folders['tenant']);
    }

    public function test_folders_skips_missing_subfolders(): void
    {
        rmdir($this->base.'/tenant');

        $folders = MigrationPaths::folders($this->base);

        $this->assertCount(1, $folders['central']);
        $this->assertSame([], $folders['tenant'], 'Missing folder list mein nahi aana chahiye.');
    }

    public function test_folders_returns_empty_lists_for_a_missing_base(): void
    {
        $folders = MigrationPaths::folders($this->base.'/nope');

        $this->assertSame(['central' => [], 'tenant' => []], $folders);
    }

    public function test_folders_tolerates_trailing_separator_in_base(): void
    {
        $folders = MigrationPaths::folders($this->base.DIRECTORY_SEPARATOR);

        $this->assertSame([$this->base.DIRECTORY_SEPARATOR.'central'], $folders['central']);
    }

    public function test_tenancy_off_registers_central_and_tenant(): void
    {
        $paths = MigrationPaths::forMigrate(MigrationPaths::folders($this->base), false);

        $this->assertSame([
            $this->base.DIRECTORY_SEPARATOR.'central',
            $this->base.DIRECTORY_SEPARATOR.'tenant',
        ], $paths);
    }

    public function test_tenancy_on_registers_central_only(): void
    {
        $paths = MigrationPaths::forMigrate(MigrationPaths::folders($this->base), true);

        $this->assertSame([$this->base.DIRECTORY_SEPARATOR.'central'], $paths);
    }

    public function test_for_migrate_does_not_duplicate_identical_paths(): void
    {
        $folders = ['central' => ['/a/central'], 'tenant' => ['/a/central', '/a/tenant']];

        $this->assertSame(['/a/central', '/a/tenant'], MigrationPaths::forMigrate($folders, false));
    }
}
