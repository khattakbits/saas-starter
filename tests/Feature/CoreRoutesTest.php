<?php

namespace Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use KhattakBits\Core\Http\Middleware\EnsureUserActive;
use KhattakBits\Core\Routing\CoreRoutes;
use Tests\TestCase;

/**
 * D3 (`03` D3.1, `07` §3.4): core routes ka split, dono flag values par.
 *
 * | `khattakbits.tenancy.enabled` | tenant routes | Central |
 * |---|---|---|
 * | `false` (CP, starter kit, tests) | bina prefix — **aaj ke URLs wohi** | wohi |
 * | `true` | `/{tenant}/...`, `{tenant}` pehla param | wohi |
 *
 * Flag=true ka route table `CoreRoutes::register(true)` se banaya jata hai, app ko
 * dobara boot kar ke nahi: boot par D2 ka guard (`flag=true` + `stancl/tenancy`
 * missing) RuntimeException deta hai — jo apni jagah sahi hai, magar route
 * registration ko test karne se rokta hai. Registration ka faisla sirf flag par hai,
 * is liye seedha call karna wohi cheez verify karta hai.
 */
class CoreRoutesTest extends TestCase
{
    /** 6 guest + 3 central auth + 6 tenant-scoped. */
    private const CORE_ROUTE_COUNT = 15;

    /** @var list<string> */
    private const CENTRAL_URIS = [
        'login',
        'forgot-password',
        'reset-password/{token}',
        'reset-password',
        'logout',
        'profile',
        'profile/password',
    ];

    /** @var list<string> */
    private const TENANT_URIS = [
        'attachments',
        'attachments/{attachment}',
        'documents/{type}/{id}/views',
        'documents/{type}/{id}/activity',
    ];

    private ?Router $router = null;

    /** @return list<string> */
    private function registerWith(bool $tenancyEnabled): array
    {
        $this->router = new Router($this->app['events'], $this->app);

        $original = $this->app->make('router');
        $this->app->instance('router', $this->router);
        Route::clearResolvedInstances();

        CoreRoutes::register($tenancyEnabled);

        $uris = [];
        foreach ($this->router->getRoutes() as $route) {
            $uris[] = $route->uri();
        }

        // Baqi test ke liye asli router wapas
        $this->app->instance('router', $original);
        Route::clearResolvedInstances();

        sort($uris);

        return array_values(array_unique($uris));
    }

    /** Unique URIs nahi — poore routes (GET+POST ek hi URI par do routes hain). */
    private function routeCount(): int
    {
        return $this->router?->getRoutes()->count() ?? 0;
    }

    /* ---------- flag = false: aaj ke URLs ---------- */

    public function test_tenancy_off_keeps_todays_uris_without_any_tenant_prefix(): void
    {
        $uris = $this->registerWith(false);

        foreach (self::CENTRAL_URIS as $uri) {
            $this->assertContains($uri, $uris, "Central URI [{$uri}] tenancy off par bhi hona chahiye.");
        }

        foreach (self::TENANT_URIS as $uri) {
            $this->assertContains($uri, $uris, "Tenant-scoped URI [{$uri}] tenancy off par bina prefix chalti hai.");
        }

        $this->assertSame([], array_values(array_filter(
            $uris,
            static fn (string $uri): bool => str_contains($uri, '{tenant}'),
        )), 'Tenancy off par koi bhi route {tenant} ke andar nahi jani chahiye.');
    }

    public function test_tenancy_off_registers_exactly_the_same_number_of_core_routes(): void
    {
        $this->registerWith(false);

        $this->assertSame(self::CORE_ROUTE_COUNT, $this->routeCount());
    }

    /* ---------- flag = true: tenant routes {tenant} ke andar ---------- */

    public function test_tenancy_on_moves_only_the_tenant_scoped_routes_under_tenant(): void
    {
        $uris = $this->registerWith(true);

        foreach (self::TENANT_URIS as $uri) {
            $this->assertContains("{tenant}/{$uri}", $uris, "[{$uri}] tenancy on par {{tenant}} ke andar jani chahiye.");
            $this->assertNotContains($uri, $uris, "[{$uri}] tenancy on par bina prefix nahi rehni chahiye.");
        }
    }

    public function test_tenancy_on_keeps_central_routes_central(): void
    {
        $uris = $this->registerWith(true);

        foreach (self::CENTRAL_URIS as $uri) {
            $this->assertContains($uri, $uris, "Central URI [{$uri}] tenancy on par bhi central rehti hai.");
            $this->assertNotContains("{tenant}/{$uri}", $uris, "Central URI [{$uri}] ko prefix nahi milna chahiye.");
        }
    }

    public function test_tenant_is_always_the_first_segment(): void
    {
        $uris = $this->registerWith(true);

        $prefixed = array_values(array_filter(
            $uris,
            static fn (string $uri): bool => str_contains($uri, '{tenant}'),
        ));

        $this->assertNotEmpty($prefixed);

        foreach ($prefixed as $uri) {
            $this->assertStringStartsWith(
                '{tenant}/',
                $uri,
                "[{$uri}]: {{tenant}} pehla param hona lazmi hai — warna InitializeTenancyByPath usay nahi parhta (07 §1.2).",
            );
        }
    }

    public function test_both_modes_register_the_same_number_of_routes(): void
    {
        $this->registerWith(false);
        $off = $this->routeCount();

        $this->registerWith(true);
        $on = $this->routeCount();

        $this->assertSame($off, $on, 'Prefix badalne se route count nahi badalna chahiye.');
        $this->assertSame(self::CORE_ROUTE_COUNT, $on);
    }

    /* ---------- middleware dono modes mein wohi ---------- */

    public function test_tenant_scoped_routes_keep_their_middleware_when_prefixed(): void
    {
        $this->registerWith(true);

        $route = collect($this->router->getRoutes()->getRoutes())
            ->first(fn ($r): bool => $r->uri() === '{tenant}/attachments' && in_array('POST', $r->methods(), true));

        $this->assertNotNull($route, '{tenant}/attachments (POST) register nahi hui.');

        $middleware = $route->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains(EnsureUserActive::class, $middleware);
        $this->assertContains('throttle:30,1', $middleware);
    }

    public function test_central_login_route_keeps_guest_and_throttle(): void
    {
        $this->registerWith(true);

        $route = collect($this->router->getRoutes()->getRoutes())
            ->first(fn ($r): bool => $r->uri() === 'login' && in_array('POST', $r->methods(), true));

        $this->assertNotNull($route, 'POST login register nahi hui.');
        $this->assertContains('guest', $route->gatherMiddleware());
        $this->assertContains('throttle:login', $route->gatherMiddleware());
    }

    public function test_route_names_are_unchanged_by_the_split(): void
    {
        $this->registerWith(true);

        $names = [];
        foreach ($this->router->getRoutes() as $route) {
            if ($route->getName() !== null) {
                $names[$route->getName()] = $route->uri();
            }
        }

        // Sirf yeh 5 core routes ke naam hain (aaj bhi) — split se koi naam nahi badla.
        ksort($names);

        $this->assertSame([
            'login' => 'login',
            'password.email' => 'forgot-password',
            'password.request' => 'forgot-password',
            'password.reset' => 'reset-password/{token}',
            'password.update' => 'reset-password',
        ], $names);
    }
}
