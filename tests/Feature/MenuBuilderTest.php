<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use KhattakBits\Core\Support\MenuBuilder;
use ReflectionMethod;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * D3 (`03` D3.2, `07` §5.3): menu contract — `path` static, `href` runtime.
 *
 * `path` route registration ke liye hai (slug-free), `href` sidebar link ke liye.
 * Tenancy off par dono barabar hone chahiye — yani aaj ke URLs bilkul wohi.
 */
class MenuBuilderTest extends TestCase
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

    private function userWith(array $permissions): User
    {
        $user = User::forceCreate([
            'name' => 'Menu User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password-12345',
            'is_active' => true,
        ]);

        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function menuFor(User $user): array
    {
        $menu = app(MenuBuilder::class)->for($user);

        return collect($menu)->keyBy('key')->all();
    }

    /* ---------- (1) payload: path + href ---------- */

    public function test_every_item_exposes_path_and_href(): void
    {
        $menu = $this->menuFor($this->userWith(['settings.users.view']));

        $this->assertNotEmpty($menu);

        foreach ($menu as $module) {
            $this->assertArrayHasKey('path', $module, "Module [{$module['key']}] par path missing.");
            $this->assertArrayHasKey('href', $module, "Module [{$module['key']}] par href missing.");

            foreach ($module['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $this->assertArrayHasKey('path', $item, "Item [{$item['label']}] par path missing.");
                    $this->assertArrayHasKey('href', $item, "Item [{$item['label']}] par href missing.");
                }
            }
        }
    }

    public function test_tenancy_off_makes_href_equal_path_so_urls_do_not_change(): void
    {
        $menu = $this->menuFor($this->userWith(['settings.users.view', 'settings.audit.view']));

        foreach ($menu as $module) {
            $this->assertSame($module['path'], $module['href'], "Module [{$module['key']}]: href aur path barabar hone chahiye.");

            foreach ($module['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $this->assertSame(
                        $item['path'],
                        $item['href'],
                        "Item [{$item['label']}]: tenancy off par href = path.",
                    );
                }
            }
        }
    }

    public function test_paths_are_static_and_never_carry_a_tenant_slug(): void
    {
        $menu = $this->menuFor($this->userWith(['settings.users.view']));

        foreach ($menu as $module) {
            $this->assertStringStartsWith('/', $module['path'], 'path absolute hona chahiye.');

            foreach ($module['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $this->assertStringStartsWith('/', $item['path']);
                    $this->assertStringNotContainsString('{tenant}', $item['path']);
                }
            }
        }
    }

    public function test_module_entry_points_at_the_first_permitted_item(): void
    {
        $menu = $this->menuFor($this->userWith(['settings.users.view']));

        $settings = $menu['settings'];

        $this->assertSame('/settings/users', $settings['path']);
        $this->assertSame('/settings/users', $settings['href']);

        // Sirf woh items jin ka haq hai — Roles/Recycle Bin etc. is user ke paas nahi.
        $labels = array_map(
            static fn (array $i): string => $i['label'],
            $settings['sections'][0]['items'],
        );
        $this->assertSame(['Users'], $labels);
    }

    /* ---------- (2) menu.php files ne contract follow kiya ---------- */

    public function test_every_module_menu_declares_path_and_drops_href(): void
    {
        $files = glob(app_path('Modules/*/menu.php')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            $name = basename(dirname($file));

            $this->assertStringNotContainsString(
                "'href' =>",
                $contents,
                "{$name}/menu.php mein `href` bacha hua hai — route registration `path` par hoti hai (`03` D3.2).",
            );
            $this->assertStringContainsString("'path' =>", $contents, "{$name}/menu.php mein `path` key nahi hai.");
        }
    }

    public function test_accounting_demo_routes_are_registered_from_path(): void
    {
        $menu = require app_path('Modules/AccountingDemo/menu.php');

        $menuPaths = [];
        foreach ($menu['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $menuPaths[] = ltrim($item['path'], '/');
            }
        }

        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r): string => $r->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, 'accounting-demo/'))
            ->values()
            ->all();

        sort($menuPaths);
        sort($registered);

        $this->assertSame(
            $menuPaths,
            $registered,
            'Har menu `path` ke liye route register hona chahiye — warna sidebar link 404 dega.',
        );
    }

    /* ---------- (3) purane menus (sirf href) chalte rehte hain ---------- */

    public function test_legacy_href_only_item_still_resolves_a_path(): void
    {
        // v1.x ke menu mein `path` nahi tha, `href` hi static path tha. Woh fallback
        // bana hua hai taake purane products upgrade par foran na tootein.
        $method = new ReflectionMethod(MenuBuilder::class, 'pathFor');
        $builder = app(MenuBuilder::class);

        $this->assertSame('/legacy/url', $method->invoke($builder, ['label' => 'Legacy', 'href' => '/legacy/url']));
        $this->assertSame('/new/url', $method->invoke($builder, ['label' => 'New', 'path' => '/new/url', 'href' => null]));
        $this->assertSame('/', $method->invoke($builder, ['label' => 'Broken']));
    }
}
