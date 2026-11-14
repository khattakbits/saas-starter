# 10 — New Product Playbook (final stack)

**Date:** 13 Nov 2026 · **Status:** design + runbook (v1) · **Repo:** `E:/LaraProjects/saas-starter`
**Depends on:** `02` (§0 D1–D5, §4 versioning, §5.5), `03` (Part D), `04` (§3, §6), `05` (§1–§3, §7.1), `06` (§0.6, §7), `07` (§2–§7, §12), `08` (§2), `09`
**Supersedes:** `docs/04-DEVELOPMENT-GUIDE.md` (12 Nov 2026) — woh path-based tenancy aur package split se **pehle** likhi gayi thi.

Yeh document **naye product ko 0 se production-ready** karne ka runbook hai. Har section ek step hai, aur har step ke saath woh **file paths** hain jinmein code jata hai. Jahan code maujood nahi, wahan **NEW** likha hai.

---

## 0. Authority — kaunsi file parhein

### 0.1 `10` vs `docs/04-DEVELOPMENT-GUIDE.md`

| File | Status | Kyun |
|---|---|---|
| **`docs/platform/10-NEW-PRODUCT-PLAYBOOK.md`** (yeh file) | ✅ **AUTHORITATIVE** | Path-based tenancy (`07`), `central/tenant` migration folders (`03` Part D), `khattakbits/sso-client` (`08`), manifest sync (`04` §6), `make:module` v2 — sab ismein |
| `docs/04-DEVELOPMENT-GUIDE.md` | ⚠️ **PARTIALLY SUPERSEDED** 13 Nov 2026 | §1 (clone), §3 (folder/naming), §9 (Definition of Done) **abhi bhi valid** hain. §2.3 (`make:module` ka purana code), §6 (plan gating), §7 (tenant testing), §10 (timeline) **is file se replace ho gaye** |

`docs/04-DEVELOPMENT-GUIDE.md` ko delete nahi kiya gaya — uske §1.2 ke clone commands aur §3.3 ke naming conventions yahan repeat nahi kiye, sirf reference diye hain.

### 0.2 Yeh playbook kya hai / kya nahi

| Yeh hai | Yeh nahi hai |
|---|---|
| Naye product ko register karne, scaffold karne, tenancy wire karne, aur CP se sync karne ka **order** | CP ka design (`04`) ya identity hub ka spec (`05`) |
| Har step ke liye **exact file path + code** | Deployment commands — woh `09` mein hain |
| "Done" ka **testable** definition (§9) | Product ka business logic (modules, reports) — woh product ka kaam hai |

### 0.3 Stack — ek page

Saat decisions jo naye product ka poora shape tay karte hain. Agar inmein se koi ek bhi chhoot gaya, product baad mein refactor ban jata hai.

| # | Decision | Kahan locked | Agar galat kiya to |
|---|---|---|---|
| **S1** | **Path-based tenancy** — `mizaanbitserp.com/{tenant}/...`, `{tenant}` **pehla** route param | `07` §0, §3.1 | `InitializeTenancyByPath` `parameterNames()[0]` padhta hai — `/attachments/{tenant}/{id}` **chupchaap non-tenant route** ban jata hai aur central DB par attachment dhoondta hai |
| **S2** | **`khattakbits/sso-client` alag package** — `core` mein nahi | `08` §2.1 | `core` product-agnostic kernel hai; SSO usme daalne se single-tenant starter kit bhi CP par depend karne lagta hai |
| **S3** | **Migration folders `central/` + `tenant/`** — do context, do commands | `07` §7.1, `03` Part D | `loadMigrationsFrom` **saari** migrations dono jagah chalata hai → `users` har tenant DB mein, `attachments` central mein |
| **S4** | **`kb-product.json` = product ka catalog contract**, CP import karta hai | `04` §6 | Catalog haath se CP admin mein type karna = drift; `plan_entitlements.product_feature_id` `null` ho jata hai aur typing fallback par chalti hai |
| **S5** | **`make:module` v2** — central/tenant migration, permissions, entitlement gate, test | yeh file §6 | Har module 30 min bachane ki jagah 30 min debugging deta hai; permission key bhool jaye to 403, migration galat folder mein jaye to tenant DB mein table nahi |
| **S6** | **`GET` routes read-only CI gate** | `05` §7.1 | `read_only` mode bypass — grace period mein bhi naya module data likhta rehta hai, koi guard nahi pakadta |
| **S7** | **Entitlement server-side, 3 jagah** | `08` §2.13, `04` §3 | Sirf Vue mein check karna = devtools se poora product free |

### 0.4 Jo stack mein **nahi** hai (aur kyun)

| Cheez | Kyun nahi | Kahan |
|---|---|---|
| **Wildcard DNS / wildcard TLS** | Path-based mein ek host, ek cert, HTTP-01. Wildcard = DNS-01 + DNS API token, bina kisi faide ke | `09` §5, `07` §0 |
| **`sso_handoffs` / handoff token** | Same origin, ek cookie. Handoff Phase 2 custom domains ke liye retire hua | `02` §5.4.1 (retired), `07` §3.6 |
| **`stancl/tenancy` CP mein** | CP single-DB hai. Multi-DB tenancy **product apps** mein hai | `04` §7 |
| **`CacheTenancyBootstrapper`** | `CACHE_STORE=file` taggable nahi → `Repository::tags()` throws. Tests `array` store use karte hain jo taggable **hai** → tests pass, prod 500 | `07` §1.5 (O2) |
| **`Organization` table** | Billing per-product hai; `billing_profile` JSON + invoice snapshot kaafi hai | `04` §8 D3 |
| **Local password login (tenant users)** | D5 — sirf break-glass admin, flag default off | `02` §0 |
| **`v-html`** | Path-based mein XSS ka blast radius poora product hai. CSP enforce + zero `v-html` non-negotiable | `07` §4.2 (M1, M3) |
| **Laravel Octane** | `URL::defaults` request ke baad leak karta hai — tenant A ka base URL tenant B ko | `07` §12.2 R15 (`NOT VERIFIED` — use na karein) |

---

## 1. Day 0 — repo, rename, packages

### 1.1 Clone

```bash
# naya product repo — skeleton se, history ke saath
git clone git@github.com:khattakbits/product-skeleton.git mizaanbits-erp
cd mizaanbits-erp
git remote set-url origin git@github.com:khattakbits/mizaanbits-erp.git
git remote add skeleton git@github.com:khattakbits/product-skeleton.git

composer install
npm ci
cp .env.example .env
php artisan key:generate
```

> Clone aur rename ka poora checklist `docs/04-DEVELOPMENT-GUIDE.md` §1.2 aur §2.1 mein hai — woh section **supersede nahi hua**, wahi follow karein.

### 1.2 `composer.json` — `vcs`, `path` nahi

Yeh **sab se pehle** theek karna hai, kyunke `type: path` local dev mein "kaam karta hai" aur CI mein toota symlink deta hai.

```json
// composer.json — product repo root
{
    "name": "khattakbits/mizaanbits-erp",
    "type": "project",
    "repositories": [
        { "type": "vcs", "url": "git@github.com:khattakbits/core.git" },
        { "type": "vcs", "url": "git@github.com:khattakbits/sso-client.git" },
        { "type": "vcs", "url": "git@github.com:khattakbits/ui.git" }
    ],
    "require": {
        "php": "^8.3",
        "laravel/framework": "^13.17",
        "khattakbits/core": "^2.0",
        "khattakbits/sso-client": "^2.0",
        "laravel/passport": "13.10.*"
    }
}
```

**`type: path` ka asli masla:** `symlink: true` ke saath `composer install` **"successful"** dikhata hai, magar `vendor/khattakbits/core` ek **toota symlink** hota hai (`../khattakbits-core` jo CI runner par maujood nahi). Autoload classmap banti nahi, aur error `Class "KhattakBits\Core\..." not found` aata hai — jo package ka masla lagta hai, composer ka nahi. Diagnose:

```bash
ls -la vendor/khattakbits/          # symlink dikhe to `path` repo abhi bhi active hai
composer why khattakbits/core       # kaun require kar raha hai
```

Local dev mein package ke against test karna ho to **commit na karein** (`02` §4.3):

```json
// composer.json — TEMPORARY, commit NA karein
"repositories": [
    { "type": "path", "url": "../khattakbits-core", "options": { "symlink": true } }
]
```

```bash
composer require "khattakbits/core:*@dev" --with-dependencies
php artisan vendor:publish --tag=khattakbits-config --force
npm run build
```

Release se pehle `*@dev` hata kar `^2.0` par wapas. `vite.config.js` mein `server.fs.allow` mein `../khattakbits-core` chahiye, warna dev server 403 deta hai.

### 1.3 Demo modules strip

```bash
# reference code product mein nahi jata — 02 §2.2 ka note
rm -rf app/Modules/Examples app/Modules/AccountingDemo
# Home rakh sakte hain (landing) ya apna banao
```

`app/Modules/Settings/**` **rakhein** — woh asal mein core hai (`02` §2.2 ka note), admin surface wahi se aata hai.

### 1.4 Identity — pehli commit

| File | Kya badalna hai |
|---|---|
| `config/app.php` | `'name' => 'Mizaan ERP'`, `'url' => env('APP_URL')` |
| `.env` | `APP_NAME`, `APP_URL=https://mizaanbitserp.com`, `SESSION_DOMAIN=` (**khali** — host-only, path-based ka faida) |
| `package.json` | `"name": "@khattakbits/mizaanbits-erp"` |
| `resources/js/Pages/Home.vue` | product branding |
| `README.md` | product ka, skeleton ka nahi |

> **`SESSION_DOMAIN` khali kyun:** path-based mein ek hi origin hai. `SESSION_DOMAIN=.mizaanbitserp.com` set karna cookie ko `www` aur `api` jaise hosts par bhi bhej deta hai — bina kisi faide ke blast radius barhta hai. `09` §7.2.

---

## 2. Migration folders — `central/` aur `tenant/`

### 2.1 Layout

Do hi valid folders hain. **Teesra nahi.**

```text
vendor/khattakbits/core/database/migrations/
├── central/       ← 2   users, password_reset_tokens, sessions  +  admin columns
└── tenant/        ← 10  attachments, settings, audit_logs (+2 alters), deleted_documents,
                         document_sequences, document_views, email_accounts, email_logs,
                         email_templates

database/migrations/            ← host (package mein nahi)
├── central/       ← 2   cache+cache_locks, jobs+job_batches+failed_jobs
└── tenant/        ← 1   permissions, roles, model_has_*, role_has_permissions

app/Modules/{Module}/Database/Migrations/
├── central/       ← agar module ki koi central table ho (rare)
└── tenant/        ← module ka data (default)
```

**Ginti `07` §2.3 ke per-table faisle se derive hoti hai, guess se nahi:** core 12 → **2 central / 10 tenant**; host 3 → **2 central / 1 tenant**. Total **4 central, 11 tenant**.

### 2.2 Provider wiring

```php
// vendor/khattakbits/core/src/CoreServiceProvider.php — boot()
$registry = $this->app->make(ModuleRegistry::class);

// 1) CENTRAL — `php artisan migrate` inhi ko chalata hai
$this->loadMigrationsFrom($this->packagePath('database/migrations/central'));

foreach ($registry->migrationPaths('central') as $path) {
    $this->loadMigrationsFrom($path);
}

// 2) TENANT — `loadMigrationsFrom` se register NAHI karenge, warna
//    `php artisan migrate` unhein bhi central DB par chala dega.
if (class_exists(\Stancl\Tenancy\Tenancy::class)) {
    $params = config('tenancy.migration_parameters');

    $params['--path'] = array_values(array_filter(
        array_merge(
            [$this->packagePath('database/migrations/tenant')],
            array_values($registry->migrationPaths('tenant')),
        ),
        'is_dir',
    ));

    config(['tenancy.migration_parameters' => $params]);
}
```

```php
// vendor/khattakbits/core/src/Support/ModuleRegistry.php — naya method
/**
 * @param  'central'|'tenant'  $context
 * @return array<string,string>  module key => absolute path
 */
public function migrationPaths(string $context): array
{
    if (! in_array($context, ['central', 'tenant'], true)) {
        throw new \InvalidArgumentException("Unknown migration context [{$context}].");
    }

    $paths = [];

    foreach ($this->all() as $key => $module) {
        $dir = $module['path'].'/Database/Migrations/'.$context;
        if (is_dir($dir)) {
            $paths[$key] = $dir;
        }
    }

    return $paths;
}
```

### 2.3 Teen traps — teenon verified hain

| # | Trap | Sahi |
|---|---|---|
| 1 | `config(['tenancy.migration_parameters.--path' => ...])` | **Kaam nahi karta.** `Arr::set()` dot ko nesting samajhta hai — `'--path'` do level ban jata hai. **Poora array** replace karein |
| 2 | `class_exists` guard na lagana | `core` ko pata nahi hona chahiye ke product multi-tenant hai — warna single-tenant starter kit `stancl/tenancy` par depend karne lagta hai |
| 3 | `--path` ko string samajhna | Woh `VALUE_IS_ARRAY` hai. `MigrateCommand` `--path` milne par **sirf** wohi paths use karta hai (`BaseCommand::getMigrationPaths()`), `migrator->paths()` ignore karta hai — core + saare modules ki tenant migrations **usi ek array** mein |

### 2.4 Filename immutability — sab se aasan galti

Laravel migration ko **filename** se match karta hai, path se nahi. Folder badalna theek hai, **filename badalna kabhi nahi**.

Agar `2026_10_26_113043_create_attachments_table.php` ko `tenant/` mein move karte waqt rename kar diya, to `tenants:migrate` usay **pending** samajh kar dobara chalayega aur `table already exists` par fail hoga — **har tenant par**, provisioning ke beech mein.

```bash
# sahi
git mv database/migrations/2026_10_26_113043_create_attachments_table.php \
       database/migrations/tenant/2026_10_26_113043_create_attachments_table.php

# ghalat — timestamp ya naam touch hua
git mv ... database/migrations/tenant/2026_10_26_120000_create_attachments_table.php
```

### 2.5 Split test — permanent rehna chahiye

Split ki galti **sirf runtime par** dikhti hai. Yeh test D2 ke baad hamesha chalega.

```php
// tests/Feature/Tenancy/MigrationSplitTest.php  (NEW)
namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

final class MigrationSplitTest extends TenantTestCase
{
    public function test_tenant_database_has_tenant_tables_and_no_central_tables(): void
    {
        $tables = collect(DB::connection('tenant')
            ->select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()'))
            ->pluck('name')
            ->all();

        // tenant DB mein yeh HONI chahiye
        foreach (['attachments', 'settings', 'audit_logs', 'deleted_documents',
                  'document_sequences', 'document_views', 'email_accounts',
                  'email_logs', 'email_templates', 'roles', 'permissions'] as $table) {
            $this->assertContains($table, $tables, "Tenant DB mein [{$table}] nahi hai.");
        }

        // aur yeh NAHI honi chahiye
        foreach (['users', 'password_reset_tokens', 'sessions', 'cache', 'jobs', 'failed_jobs'] as $table) {
            $this->assertNotContains($table, $tables, "Tenant DB mein [{$table}] nahi honi chahiye.");
        }
    }

    public function test_central_database_has_no_tenant_tables(): void
    {
        $tables = collect(DB::connection('central')
            ->select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()'))
            ->pluck('name')
            ->all();

        $this->assertContains('users', $tables);
        $this->assertContains('sessions', $tables);
        $this->assertNotContains('attachments', $tables);
        $this->assertNotContains('audit_logs', $tables);
    }
}
```

> **`sessions` ka ulat-pher:** woh `create_users_table.php` ke **andar** banti hai, yani `central/` mein. Iska matlab: **tenant DB mein `sessions` table hogi hi nahi**. Is liye `session.connection = 'central'` pin karna optional nahi, **lazmi** hai (§3.5).

---

## 3. Path-based tenancy — product ke andar

### 3.1 Route structure

```php
// routes/web.php — product ka
use Illuminate\Support\Facades\Route;
use KhattakBits\Core\Http\Middleware\EnsureUserActive;
use KhattakBits\Core\Support\ModuleRegistry;
use KhattakBits\SsoClient\Http\Middleware\SubscriptionGuard;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
 * 1) CENTRAL routes — pehle register, taake /login jaisa path
 *    /{tenant}/... se pehle match ho.
 */
Route::middleware('web')->group(function () {
    // SSO callback, billing return, webhook receiver — sso-client khud register karta hai
});

/*
 * 2) TENANT routes — {tenant} PEHLA parameter lazmi.
 */
Route::prefix('{tenant}')
    ->middleware([
        'web',
        'auth',
        EnsureUserActive::class,
        InitializeTenancyByPath::class,
        EnsureTenantMembership::class,
        SubscriptionGuard::class,
    ])
    ->group(function () {
        foreach (app(ModuleRegistry::class)->all() as $module) {
            if (is_file($file = $module['path'].'/routes.php')) {
                require $file;
            }
        }
    });
```

**Do baatein jo aasan lagti hain magar nahi hain:**

1. **Module ka `routes.php` mein `{tenant}` kabhi nahi likhein.** `Route::prefix('{tenant}')` pehle se lag chuka hai. Andar `{tenant}` dobara likhne se path `/{tenant}/{tenant}/...` ban jata hai.
2. **`auth` tenancy se pehle hai.** Users central hain (D1), is liye auth ko tenant ki zaroorat nahi. `EnsureTenantMembership` tenancy ke **baad** hai kyunke usay `tenant()` chahiye.

### 3.2 Middleware order — exact

| # | Middleware | Kahan se | Kyun is jagah |
|---|---|---|---|
| 1 | `EncryptCookies`, `AddQueuedCookiesToResponse` | `web` | Sab se pehle |
| 2 | `StartSession` | `web` | Auth se pehle |
| 3 | `ShareErrorsFromSession`, `ValidateCsrfToken` | `web` | — |
| 4 | `SubstituteBindings` | `web` | `{tenant}` ka koi binding nahi, is liye string reh jata hai |
| 5 | `auth` | route group | Central user — tenancy ki zaroorat nahi |
| 6 | `EnsureUserActive` | route group | Disabled user → logout |
| 7 | **`InitializeTenancyByPath`** | route group | `{tenant}` → slug lookup → DB switch → `URL::defaults` |
| 8 | **`EnsureTenantMembership`** | route group | `kb_user_tenant` check — `tenant()` chahiye, is liye 7 ke baad |
| 9 | `SubscriptionGuard` | route group | `access_mode` → read-only/locked |
| 10 | route middleware (`can:`, `entitled:`) | routes | Tenant DB ke roles + snapshot chahiye |

**Order badalne ka natija:** `EnsureTenantMembership` ko 7 se pehle rakhne par `tenant()` `null` hota hai → har request 500. `SubscriptionGuard` ko `auth` se pehle rakhne par user hi nahi milta.

### 3.3 Reserved slugs — routes se derive, static list se nahi

`02` §5.4 ki purani list (`www, app, admin, api, auth, ...`) **guess** thi. Sahi tareeqa: central routes se root segments nikaalo, aur ek **coverage test** drift roke.

```php
// tests/Feature/Tenancy/ReservedSlugCoverageTest.php  (NEW)
namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ReservedSlugCoverageTest extends TestCase
{
    public function test_every_central_root_segment_is_reserved(): void
    {
        $reserved = config('tenancy.reserved_slugs');   // app-level list

        $segments = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($r) => str_starts_with(ltrim($r->uri(), '/'), '{tenant}'))
            ->map(fn ($r) => explode('/', ltrim($r->uri(), '/'))[0])
            ->filter(fn ($s) => $s !== '' && ! str_starts_with($s, '{'))
            ->unique()
            ->values();

        foreach ($segments as $segment) {
            $this->assertContains(
                $segment, $reserved,
                "Central route [{$segment}] reserved list mein nahi hai — tenant is slug ko hijack kar sakta hai."
            );
        }
    }
}
```

**Naya tenant banao to slug validation `07` §3.3 ke derived list + policy blocklist se guzre** — sirf `unique(product_id, slug)` kaafi nahi.

### 3.4 `tenantUrl()` — ek helper, teen jagah

| Jagah | Helper | Example |
|---|---|---|
| PHP (controller/redirect) | `tenant_url($slug, '/dashboard')` | `redirect()->to(tenant_url($slug, '/dashboard'))` |
| Inertia shared prop | `props.tenant.base` = `/acme` | `HandleInertiaRequests::share()` |
| Vue | `useTenantUrl().tenantPath('/settings/users')` | → `/acme/settings/users` |

```php
// vendor/khattakbits/core/src/Support/TenantUrl.php
namespace KhattakBits\Core\Support;

final class TenantUrl
{
    public static function to(?string $slug, string $path = '/', bool $absolute = false): string
    {
        $slug = $slug ?? tenant()?->slug;

        if ($slug === null) {
            throw new \RuntimeException('tenantUrl() called without tenant context or slug.');
        }

        $path = '/'.ltrim($path, '/');
        $path = $path === '/' ? '' : $path;

        $base = self::baseFor($slug);

        return $absolute
            ? rtrim($base, '/').$path
            : '/'.$slug.$path;
    }

    private static function baseFor(string $slug): string
    {
        // Phase 2: custom domain ho to host yahan badlega. Koi caller nahi badlega.
        return rtrim((string) config('app.url'), '/');
    }
}
```

**Design rule jo code review mein enforce hoga:**

| Cheez | Helper |
|---|---|
| Tenant page ka link, `router.visit`, form action, redirect | `tenantPath()` / `tenant_url()` |
| `/login`, `/logout`, `/profile`, `/forgot-password` | `centralPath()` / plain string |
| External URL | plain string (koi helper nahi) |

### 3.5 Connection pinning — yeh table na ho to silent data corruption

`DatabaseTenancyBootstrapper` `database.default` ko `tenant` par switch karta hai. Jo bhi subsystem tenancy init ke **baad** connection resolve karega, woh tenant DB pakdega.

```php
// config/database.php
'default' => env('DB_CONNECTION', 'central'),

'connections' => [
    'central' => [ /* mysql, DB_DATABASE=khattakbits_mizaan_central */ ],
    'tenant'  => [ /* template — bootstrapper isay per-tenant clone karta hai */ ],
],
```

```php
// config/session.php
'connection' => env('SESSION_CONNECTION', 'central'),
```

```php
// config/queue.php — database connection
'connection' => env('DB_QUEUE_CONNECTION', 'central'),
```

| Config | Ghalat | Natija |
|---|---|---|
| `database.default` | `mysql` | Bootstrapper ke paas switch karne ke liye template connection nahi |
| `session.connection` | `null` | `StartSession` tenancy se pehle chalta hai is liye aaj theek — magar koi bhi resolve-order change session ko tenant DB mein le jayega, jahan `sessions` table **hai hi nahi** → 500 |
| `queue.connections.database.connection` | `null` | Tenant request ka job **tenant DB** mein insert hota hai; worker central se padhta hai → job kabhi nahi chalta |
| `cache.stores.database.connection` | `null` | Wahi masla cache par |

Yeh pinning ek test se enforce hogi:

```php
// tests/Feature/Tenancy/ConnectionPinningTest.php  (NEW)
public function test_session_and_queue_connections_are_pinned(): void
{
    $this->assertNotNull(config('session.connection'), 'session.connection null hai — tenant DB mein sessions table nahi hoti.');
    $this->assertNotNull(config('queue.connections.database.connection'), 'queue connection null hai — tenant job tenant DB mein jayega.');
    $this->assertSame('central', config('session.connection'));
}
```

### 3.6 Membership — URL se **kabhi** nahi

Yeh path-based ka **waahid** authorization gate hai. URL mein `{tenant}` hai, is liye URL se membership derive karna tenant-hopping khol deta hai.

```php
// vendor/khattakbits/sso-client/src/Http/Middleware/EnsureTenantMembership.php
namespace KhattakBits\SsoClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = tenant()?->getTenantKey();
        $user     = $request->user();

        if ($tenantId === null || $user === null) {
            abort(404);
        }

        $isMember = $user->tenants()
            ->whereKey($tenantId)
            ->wherePivotNull('revoked_at')
            ->exists();

        // 404, 403 nahi — tenant ka wajood leak na ho (07 §3.8)
        abort_unless($isMember, 404);

        return $next($request);
    }
}
```

**Membership ka source sirf `GET /api/v1/users/tenants` hai** (`07` §3.6.1). `ReconcileMemberships` usi se `kb_user_tenant` likhta hai; URL, product access, ya email se kabhi nahi.

**Yeh test sab se important hai** (`07` T2):

```php
public function test_url_tampering_with_tenant_a_session_returns_404(): void
{
    $this->actingAs($this->memberOf('acme'));

    $this->get('/beta/dashboard')->assertNotFound();   // 404, na 403, na 200
}
```

---

## 4. `kb-product.json` — manifest aur CP import

### 4.1 Manifest kya hai (aur kya nahi)

Manifest **product ki capability** declare karta hai — modules, features, doctypes, reports. Woh **commerce declare nahi karta**: plans, prices, trial days, seat limits sab CP ka kaam hai (`04` §4.1 screens 8–10).

| Manifest mein hai | Manifest mein **nahi** hai |
|---|---|
| `modules` → `features` → `doctypes` / `reports` | `plans`, `plan_prices`, `plan_entitlements` |
| `key`, `name`, `version`, `min_starter_version` | Product ka domain / branding (`products` row ka kaam) |
| `checksum` | OAuth client secrets, webhook secrets |

**Yeh split kyun:** product ko apni price nahi pata honi chahiye. Agar product manifest mein price bhej sake, to pricing ka source of truth do jagah ho gaya — aur `04` §3.2 ka `entitlements_hash` dono par depend karta hai.

### 4.2 Schema

```json
// kb-product.json — product repo root (generated, commit karein)
{
  "schema": 1,
  "key": "mizaan",
  "name": "Mizaan ERP",
  "version": "1.4.2",
  "min_starter_version": "2.0.0",
  "modules": [
    {
      "key": "accounting",
      "name": "Accounting",
      "description": "Sales, purchase, journals, chart of accounts",
      "is_core": true,
      "sort_order": 10,
      "features": [
        { "key": "accounting.invoices",  "name": "Invoices",  "type": "flag" },
        { "key": "accounting.periods",   "name": "Periods",   "type": "limit", "unit": "count", "default_value": "0" },
        { "key": "accounting.attachment_mb", "name": "Attachment storage", "type": "quota", "unit": "mb", "reset_period": null }
      ],
      "doctypes": [
        {
          "type": "invoice",
          "label": "Invoice",
          "model": "App\\Modules\\Accounting\\Models\\Invoice",
          "permission_prefix": "accounting.invoices",
          "url": "/accounting/invoices",
          "actions": ["view", "create", "update", "delete", "submit", "cancel"]
        }
      ],
      "reports": [
        { "key": "accounting.trial_balance", "name": "Trial Balance", "route": "/reports/trial-balance", "permission": "accounting.reports.view", "is_exportable": true }
      ]
    }
  ],
  "checksum": "sha256:9f2c...e41a"
}
```

**Field contract:**

| Field | Type | Rule |
|---|---|---|
| `schema` | int | Abhi `1`. CP unknown schema par `422` deta hai, chupchaap ignore nahi karta |
| `key` | string(40) | `products.key` se **exact** match. Lowercase, `[a-z0-9_-]`. **Immutable** |
| `version` | semver | Product ka apna version. Informational — CP isse koi behaviour nahi badalta |
| `min_starter_version` | semver | Product jis `khattakbits/core` par bana. `02` §4.2 ka `doctor` banner isse compare karta hai |
| `modules[].key` | string(60) | Module ka stable key; feature/doctype keys isi se prefix hoti hain |
| `modules[].is_core` | bool | `true` = disable nahi ho sakta (`product_modules.is_core`) |
| `features[].type` | enum | Teen values: `flag`, `limit`, `quota`. **Authoritative typing** (`04` §3.1) — `plan_entitlements.type` sirf fallback hai |
| `features[].default_value` | string | `'0'` ya `'-1'` (unlimited). Limit/quota par lazmi |
| `doctypes[].permission_prefix` | string(120) | `DocumentRegistry` ka `can` prefix |
| `doctypes[].model` | FQCN string | Sirf documentation — **CP isay load nahi karta** (warna CP product ka code execute karega) |
| `reports[].route` | string | Product-side path, slug-free |
| `checksum` | `sha256:<hex>` | §4.4 |

### 4.3 Generation — `khattakbits:manifest` (NEW)

Yeh command **maujood nahi** (`04` §6.1 ne bhi likha: "command maujood nahi — banana hai"). Yeh `make:module` (§6) ke likhe hue `manifest.php` files se manifest banata hai — haath se JSON likhna nahi.

```php
// vendor/khattakbits/core/src/Console/BuildManifest.php  (NEW)
namespace KhattakBits\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class BuildManifest extends Command
{
    protected $signature = 'khattakbits:manifest
                            {--check : Sirf verify karein ke committed file current hai}
                            {--print : stdout par likhein, file na chhuein}';

    protected $description = 'kb-product.json ko modules ke manifest.php se generate karein';

    public function handle(): int
    {
        $modules = [];

        foreach (app(\KhattakBits\Core\Support\ModuleRegistry::class)->all() as $key => $module) {
            $file = $module['path'].'/manifest.php';

            if (! is_file($file)) {
                continue;
            }

            $def = require $file;
            $def['key'] = $def['key'] ?? $key;
            $modules[] = $def;
        }

        usort($modules, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        $body = [
            'schema'               => 1,
            'key'                  => config('khattakbits.product_key'),
            'name'                 => config('khattakbits.product_name'),
            'version'              => config('khattakbits.product_version'),
            'min_starter_version'  => config('khattakbits.min_starter_version'),
            'modules'              => $modules,
        ];

        $body['checksum'] = 'sha256:'.hash('sha256', $this->canonicalJson($body));

        $json = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $path = base_path('kb-product.json');

        if ($this->option('check')) {
            $current = is_file($path) ? File::get($path) : '';

            if ($current !== $json) {
                $this->error('kb-product.json out of date hai — `php artisan khattakbits:manifest` chalayein.');

                return self::FAILURE;
            }

            $this->info('kb-product.json current hai.');

            return self::SUCCESS;
        }

        if ($this->option('print')) {
            $this->line($json);

            return self::SUCCESS;
        }

        File::put($path, $json);
        $this->info("Wrote {$path} (".count($modules).' modules)');

        return self::SUCCESS;
    }

    /**
     * Recursive ksort + no whitespace — 04 §3.3 ke canonical JSON jaisa hi.
     */
    private function canonicalJson(array $value): string
    {
        $sort = function (array $a) use (&$sort): array {
            ksort($a);

            foreach ($a as $k => $v) {
                if (is_array($v)) {
                    $a[$k] = $sort($v);
                }
            }

            return $a;
        };

        return json_encode($sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
```

```php
// app/Modules/Accounting/manifest.php — make:module isay generate karta hai (§6)
return [
    'key'        => 'accounting',
    'name'       => 'Accounting',
    'description'=> 'Sales, purchase, journals, chart of accounts',
    'is_core'    => true,
    'sort_order' => 10,
    'features'   => [
        ['key' => 'accounting.invoices', 'name' => 'Invoices', 'type' => 'flag'],
        ['key' => 'accounting.periods',  'name' => 'Periods',  'type' => 'limit', 'unit' => 'count', 'default_value' => '0'],
    ],
    'doctypes'   => [
        ['type' => 'invoice', 'label' => 'Invoice',
         'model' => \App\Modules\Accounting\Models\Invoice::class,
         'permission_prefix' => 'accounting.invoices',
         'url' => '/accounting/invoices',
         'actions' => ['view', 'create', 'update', 'delete', 'submit', 'cancel']],
    ],
    'reports'    => [
        ['key' => 'accounting.trial_balance', 'name' => 'Trial Balance',
         'route' => '/reports/trial-balance', 'permission' => 'accounting.reports.view', 'is_exportable' => true],
    ],
];
```

CI mein `--check` lazmi — warna committed manifest drift karta hai aur CP ko purana catalog milta hai:

```yaml
# .github/workflows/tests.yml
- name: Manifest is current
  run: php artisan khattakbits:manifest --check
```

### 4.4 `checksum` kaise compute hota hai (NEW)

`04` §6.1 mein `"checksum": "sha256:..."` dikhaya gaya hai magar **rule define nahi kiya**. Rule yeh hai:

1. Poore object se `checksum` key **hatao**.
2. Recursive `ksort` (har nested array par).
3. `json_encode(..., JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`, **koi whitespace nahi**.
4. `sha256:<hex>`.

Yeh **wahi canonicalization** hai jo `04` §3.3 `entitlements_hash` ke liye use karta hai. Do alag canonicalization rakhna bug ka source hai — ek hi helper (`Support\CanonicalJson`) dono jagah use ho.

**CP checksum ko dobara compute karta hai.** Match na ho → `422 checksum_mismatch`, kuch apply nahi. Wajah: `X-KB-Signature` **body** ko bind karta hai, magar agar proxy body ko re-serialize kare (whitespace badle) to signature valid reh sakti hai jabke content ka matlab badal gaya ho — checksum content-addressed hai.

### 4.5 Push mechanism

```bash
# product se CP ko manifest bhejna
BODY=$(cat kb-product.json)
TS=$(date +%s)
SECRET=$(grep '^KB_MANIFEST_SECRET=' .env | cut -d= -f2-)
SIG=$(printf '%s.%s' "$TS" "$BODY" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/^.* //')

curl -sS -X POST "https://app.khattakbits.com/api/v1/products/mizaan/manifest" \
  -H "Content-Type: application/json" \
  -H "X-KB-Product: mizaan" \
  -H "X-KB-Signature: t=$TS,v1=$SIG" \
  --data-binary "$BODY"
```

| Header | Value |
|---|---|
| `X-KB-Signature` | `t={ts},v1=HMAC_SHA256(secret, "{ts}.{raw_body}")` |
| `X-KB-Product` | product key |
| `Content-Type` | `application/json` |

| Rule | Kyun |
|---|---|
| **Secret webhook secret se alag** | Blast radius alag hai — manifest **write** karta hai, webhook **read** karta hai. Ek leak se doosra nahi khulna chahiye |
| **300s timestamp window** | Replay protection — wahi rule jo webhook receiver par hai (`08` §2.14) |
| **Raw bytes, re-serialize nahi** | `json_decode` → `json_encode` karne se signature kabhi match nahi karegi. HMAC **raw body** par hai |
| **Idempotency `products.manifest_hash`** | Same body → `200 {applied:false, warnings:[]}`. Yeh 30-minute retry loop ko safe banata hai |
| **All-or-nothing** | Validation fail → `422` with `errors[]`, ek bhi row touch nahi |

### 4.6 CP import rules (`04` §6.2–§6.4)

#### 4.6.1 Diff rules

| Manifest | DB | Action |
|---|---|---|
| Module present | absent | `INSERT status=active` |
| Module present | present | `UPDATE name/description/is_core/sort_order` only |
| Module present, `status=removed` | present | `status=active`, `removed_at=null` (module wapas aya) |
| Module **absent** | present | `status=removed`, `removed_at=now()` — **DELETE NAHI** |
| Feature/doctype/report absent | present | `status=removed` (same rule) |
| Doctype `permission_prefix` changed | — | warning + apply (permission rename product ka kaam hai) |

**Hard delete kabhi nahi.** `plan_entitlements.product_feature_id` FK aur audit history toot jati hai. Prune alag command se, 90 din baad, sirf jab koi reference na ho.

#### 4.6.2 Module removed, magar active subscriptions reference karte hain

Yeh sab se aam real case hai — product ne payroll hata diya, 40 tenants ne pay kiya hua hai.

1. `product_modules.status = 'removed'`, `removed_at = now()`. Row **zinda** rehti hai.
2. **`plan_entitlements` rows touch nahi hoti.** Warna renewal par price same rehta aur value gayab ho jati (billing mismatch).
3. **Resolution grandfather karti hai** (`04` §3.2 step 4): key sirf un subscriptions se drop hoti hai jinka `current_period_start >= removed_at`.
4. Snapshot mein `modules.payroll = "removed"` aata hai — product "sunset" badge dikha sakta hai.
5. **New sales block:** plan editor removed module ki key add karne par `422`; storefront se removed module wale plan hide (`is_public` auto-false **nahi** — plan mein doosre modules bhi hain).
6. **Admin alert:** "3 plans, 40 subscriptions reference removed module `payroll`".
7. **Deprecation window:** pehle `deprecated` (kaam karta hai, badge "sunset in N days"), 90 din baad `removed`.
8. **Prune:** `platform:prune-modules --product={key}` — sirf `removed` + 90 din purani + zero `plan_entitlements` + zero active subscription.

#### 4.6.3 Chaar validation rules jo `04` mein nahi likhi (NEW)

Yeh chaar holes hain jo import ko "signed but misrouted" bana dete hain — `08` §2.14 ne webhook par bilkul yehi class ka bug fix kiya tha.

| # | Rule | Kyun | Response |
|---|---|---|---|
| **V1** | URL ka `{key}` = `X-KB-Product` header = body ka `key` — **teenon** match karein | Warna product A apni valid signature se product B ka catalog overwrite kar sakta hai. Signature body ko bind karti hai, **route** ko nahi | `422 product_key_mismatch` |
| **V2** | `checksum` recompute kar ke compare karein (§4.4) | Proxy/re-serialization se content badal sakta hai jabke signature valid rahe | `422 checksum_mismatch` |
| **V3** | `min_starter_version` kabhi **decrease** na ho | Rollback ke waqt purana manifest push ho jaye to saare products ka `doctor` banner galat ho jata hai | `422 min_version_regression` |
| **V4** | Feature ka `type` badalna (`flag` → `limit` ya ulta) — jab us feature par `plan_entitlements` rows maujood hon | `04` §3.1 kehta hai `product_features.type` **jeet jati hai**. Yani ek manifest edit chupchaap har plan ka resolution badal deta hai — `flag` `true` tha, ab `limit 1` ban gaya | `422 feature_type_change` unless body mein `"allow_type_change": true` |

**V1 ka test** (`04` §7.1 ke T-table ke saath jaye):

```php
// CP: tests/Feature/Api/ManifestImportTest.php  (NEW)
public function test_manifest_key_must_match_url_and_header(): void
{
    $body = $this->manifestBody(key: 'lawbits');          // body lawbits
    $res = $this->postSignedJson('/api/v1/products/mizaan/manifest', $body, header: 'mizaan');

    $res->assertStatus(422)->assertJsonPath('error', 'product_key_mismatch');
    $this->assertDatabaseMissing('products', ['key' => 'lawbits']);
}

public function test_feature_type_change_requires_opt_in(): void
{
    // pehle flag, phir limit — plan_entitlements maujood
    $this->postSignedJson('/api/v1/products/mizaan/manifest', $this->manifestBody())->assertOk();
    $this->postSignedJson('/api/v1/products/mizaan/manifest', $this->manifestBody(type: 'limit'))
        ->assertStatus(422)->assertJsonPath('error', 'feature_type_change');
}
```

### 4.7 Import ke baad verification

```bash
# CP repo
php artisan tinker --execute="
\$p = App\Models\Product::where('key','mizaan')->first();
dump([
  'modules'      => \$p->modules()->count(),
  'features'     => \$p->features()->count(),
  'doctypes'     => \$p->doctypes()->count(),
  'reports'      => \$p->reports()->count(),
  'plans'        => \$p->plans()->count(),
  'entitlements' => \$p->plans()->withCount('entitlements')->get()->pluck('entitlements_count','key'),
]);
"
```

| Check | Expected | Nahi hua to |
|---|---|---|
| Har plan ke entitlements = features ki ginti | **barabar** | Koi plan feature bhool raha hai — `Entitlement::has()` us key par `false` dega, **silently** |
| Har `plan_entitlements.product_feature_id` non-null | 100% | Feature key typo hai — seeder `null` chhod dega aur typing fallback par chala jayega |
| `products.domain` set hai | `mizaanbitserp.com` | `tenant_url(absolute: true)` `config('app.url')` par gir jayega (galat host) |
| `min_starter_version` = core ka actual version | match | `doctor` outdated banner nahi dikhayega |
| OAuth client ke `redirect_uris` mein `{slug}` nahi | ✅ | Exact match fail → har login `invalid_request` |
| `webhook_endpoints.events` mein `subscription.created` | ✅ | Provisioning trigger hi nahi milega — tenant `pending` par phansa rahega |

---

## 5. `khattakbits/sso-client` — wiring

### 5.1 Package kya karta hai (aur kya `core` mein nahi jata)

| Concern | Package |
|---|---|
| OAuth2 + PKCE, state store, callback | `sso-client` |
| Mirror user + membership reconcile | `sso-client` |
| `resolve` cache, 72h offline ladder, fail modes | `sso-client` |
| `SubscriptionGuard`, `EnsureTokenFresh`, `EnsureEntitled` | `sso-client` |
| `Entitlement` helper + Vue composable | `sso-client` |
| Webhook receiver + HMAC + idempotency | `sso-client` |

`core` **product-agnostic kernel** rehta hai (audit, mailer, documents, RBAC, settings, modules). SSO usme daalne se single-tenant starter kit bhi CP par depend karne lagta hai — jo `07` §7.3 ke `class_exists` guard ka ulat hai.

### 5.2 `config/kb.php` — sirf `env()`, koi hardcode nahi

```php
// vendor/khattakbits/sso-client/config/kb.php
return [
    'cp_url'        => env('KB_CP_URL', 'https://app.khattakbits.com'),
    'client_id'     => env('KB_CLIENT_ID'),
    'client_secret' => env('KB_CLIENT_SECRET'),
    'redirect_uri'  => env('KB_REDIRECT_URI', env('APP_URL').'/auth/kb/callback'),
    'scopes'        => ['openid', 'profile', 'email', 'subscription', 'entitlements'],

    'oauth' => [
        'state_ttl' => 600,
        'cookie'    => 'kb_oauth_state',
        'silent'    => false,   // prompt=none — third-party cookie blocking isay torta hai
    ],

    'http' => [
        'timeout'         => 15,
        'connect_timeout' => 5,
        'retries'         => 2,     // sirf idempotent GET par
        'retry_delay_ms'  => 200,
    ],

    'resolve' => [
        'ttl'                   => 300,
        'negative_ttl'          => 30,
        'max_backoff'           => 300,
        'offline_max_age_hours' => 72,   // iske baad fail-CLOSED
        'degrade_after_hours'   => 24,   // 24h+ purana snapshot "full" nahi de sakta
    ],

    'fail_mode' => env('KB_FAIL_MODE', 'closed'),
];
```

### 5.3 `.env` — product ke 9 keys

```dotenv
# .env — product app
KB_CP_URL=https://app.khattakbits.com
KB_CLIENT_ID=9f2c1e8a-...
KB_CLIENT_SECRET=
KB_REDIRECT_URI=https://mizaanbitserp.com/auth/kb/callback
KB_WEBHOOK_SECRET=
KB_MANIFEST_SECRET=
KB_FAIL_MODE=closed
KB_OFFLINE_MAX_AGE_HOURS=72
KB_ENTITLEMENT_CACHE=file
```

| Key | Kahan se | Note |
|---|---|---|
| `KB_CLIENT_ID` / `KB_CLIENT_SECRET` | CP admin → OAuth clients (`04` §4.1 #21) | Secret **sirf ek dafa** dikhta hai; `secret_last4` CP mein rehta hai |
| `KB_REDIRECT_URI` | `product_oauth_clients.redirect_uris` mein **exactly** wahi | Ek character ka farq = `invalid_request` |
| `KB_WEBHOOK_SECRET` | CP admin → webhook endpoint | `webhook_endpoints.secret`, `encrypted` cast |
| `KB_MANIFEST_SECRET` | CP admin → product | **Webhook secret se alag** (§4.5) |
| `KB_FAIL_MODE` | `closed` production mein | `open` = "CP down hai to sab allow" = subscription bypass |

### 5.4 Paanch migrations — **central** mein

```text
vendor/khattakbits/sso-client/database/migrations/
├── 2026_11_20_000001_add_kb_columns_to_users_table.php      ← central (users.kb_user_id, blocked_at, token_version)
├── 2026_11_20_000002_create_kb_user_tenant_table.php        ← central
├── 2026_11_20_000003_create_kb_snapshot_table.php           ← central
├── 2026_11_20_000004_create_kb_oauth_state_table.php        ← central
└── 2026_11_20_000005_create_webhook_events_table.php        ← central
```

**Paanchon central kyun:** `users` central hai (D1), `kb_user_tenant` uske saath rehta hai, `kb_snapshot`/`kb_oauth_state`/`webhook_events` **resolve se pehle** chahiye — aur resolve se pehle tenant initialized hota hi nahi. Tenant DB mein daalne se webhook dedupe **kaam hi nahi karta** (event kis tenant ka hai, yeh payload se aata hai, URL se nahi).

Provider mein `loadMigrationsFrom` **central** folder par:

```php
// vendor/khattakbits/sso-client/src/SsoClientServiceProvider.php
$this->loadMigrationsFrom(__DIR__.'/../database/migrations/central');
```

### 5.5 Product ke sirf **2** routes

| Route | Host | Kaam |
|---|---|---|
| `GET /auth/kb/redirect` | central | `state` + PKCE verifier banata hai, CP par 302 |
| `GET /auth/kb/callback` | central | Token exchange, userinfo, `users/tenants`, `resolve`, `kb_snapshot` |

Plus `POST /webhooks/kb` (central, CSRF exempt, HMAC). **`/auth/handoff` maujood nahi** — path-based mein same-origin session pehle se hai (`02` §1.2, `07` §3.6).

**Callback membership URL se derive nahi karta** (`08` §2.9). Woh `GET /api/v1/users/tenants` se aata hai, aur redirect `tenantUrl($slug, '/dashboard')` se hota hai — jahan `$slug` CP ke response se aata hai, request se nahi.

### 5.6 `access_mode` — product apna faisla nahi karta

| `access_mode` | Product ka behaviour |
|---|---|
| `full` | Sab allowed |
| `read_only` | Naye records/edits block; read + export + "Renew" CTA allowed |
| `locked` | Sirf billing + data export |
| `blocked` | Resolver 404 deta hai, session terminate |
| **missing / unknown** | **`read_only` treat karo** |

**Unknown par `read_only` kyun, `full` ya `locked` nahi:** `full` = paying customer ko free access (revenue leak); `locked` = paying customer ka kaam ruk jaye (support ticket jo product ki ghalti nahi). `read_only` beech ka rasta hai — fail-closed for writes, fail-open for reads.

---

## 6. `make:module` generator — v2

### 6.1 Purana generator kya miss karta hai

`docs/04-DEVELOPMENT-GUIDE.md` §2.3 ka `MakeModule` **path-based tenancy se pehle** ka hai. Usmein:

| Miss | Natija |
|---|---|
| Migration `Database/Migrations/` mein likhta hai, `tenant/` mein nahi | `tenants:migrate` usay **kabhi nahi** chalayega → tenant DB mein table hi nahi → 500 |
| Menu mein `href` hi route URI hai | `href` mein slug bake ho jata hai → tenant A ki routes banti hain, tenant B ki **404** (`03` D3.2) |
| Permissions ka sirf **note** deta hai ("Permissions add karein") | Key bhoolna normal hai → 403 |
| Entitlement gating ka koi zikr nahi | Module plan ke baghair bhi chalta hai |
| Koi test nahi banata | Pehla regression product ke paas likha jata hai |
| `manifest.php` nahi banata | `kb-product.json` haath se likhna padta hai → CP catalog drift |

### 6.2 Kya produce hota hai

| # | File | Kaam |
|---|---|---|
| 1 | `app/Modules/{Module}/manifest.php` | `khattakbits:manifest` ka input (§4.3) |
| 2 | `app/Modules/{Module}/permissions.php` | Tenant seeder isay padhta hai — permission keys ek hi jagah |
| 3 | `app/Modules/{Module}/menu.php` | `path` (static) + `href` (runtime) + `can` |
| 4 | `app/Modules/{Module}/routes.php` | `{tenant}` **nahi** — host prefix lagata hai |
| 5 | `Models/{Doc}.php` | Eloquent, tenant connection implicit |
| 6 | `Http/Controllers/{Doc}Controller.php` | `index`, `data`, `store`, `update`, `destroy` |
| 7 | `Http/Requests/{Doc}Request.php` | validation |
| 8 | `Http/Resources/{Doc}Row.php` | `ListView` row shape |
| 9 | `Database/Migrations/{tenant}` ya `Database/Migrations/central` ke andar `..._create_{table}_table.php` | **cross-DB FK nahi** |
| 10 | `resources/js/Pages/{Module}/{Doc}/Index.vue` + `Form.vue` | `ListPage` / `FormPage` |
| 11 | `tests/Feature/Modules/{Module}Test.php` | tenancy + entitlement + permission |

### 6.3 Command

```php
// vendor/khattakbits/core/src/Console/MakeModule.php  (NEW — v2)
namespace KhattakBits\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeModule extends Command
{
    protected $signature = 'make:module
                            {name : Module name, e.g. Accounting}
                            {--key= : Module key (default: slug of name)}
                            {--with-doctype= : Comma separated doctypes, e.g. SalesInvoice,JournalEntry}
                            {--central : Central-context migration (default: tenant)}
                            {--entitlement= : Plan entitlement key that gates the module}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a tenancy-aware module: manifest, permissions, menu, routes, model, controller, migration, Vue pages, tests';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $key  = (string) ($this->option('key') ?: Str::slug($name));
        $base = app_path("Modules/{$name}");
        $context = $this->option('central') ? 'central' : 'tenant';

        if (is_dir($base) && ! $this->option('force')) {
            $this->error("Module [{$name}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $doctypes = collect(explode(',', (string) $this->option('with-doctype')))
            ->map(fn ($d) => Str::studly(trim($d)))
            ->filter()
            ->values();

        foreach (['', 'Database/Migrations/'.$context, 'Http/Controllers', 'Http/Requests',
                  'Http/Resources', 'Models'] as $dir) {
            $files->ensureDirectoryExists($base.($dir ? '/'.$dir : ''));
        }

        // 1) manifest.php — kb-product.json ka input
        $files->put($base.'/manifest.php', $this->manifestStub($key, $name, $doctypes));

        // 2) permissions.php — tenant seeder ka input
        $files->put($base.'/permissions.php', $this->permissionsStub($key, $doctypes));

        // 3) menu.php — path (static) + href (runtime)
        $files->put($base.'/menu.php', $this->menuStub($key, $name, $doctypes));

        // 4) routes.php — {tenant} NAHI
        $files->put($base.'/routes.php', $this->routesStub($key, $doctypes));

        // 5-8) per doctype
        foreach ($doctypes as $doc) {
            $table = $key.'_'.Str::snake(Str::pluralStudly($doc));
            $perm  = $key.'.'.Str::snake(Str::pluralStudly($doc));

            $files->put("{$base}/Models/{$doc}.php", $this->modelStub($doc, $table));
            $files->put("{$base}/Http/Controllers/{$doc}Controller.php", $this->controllerStub($key, $doc, $perm));
            $files->put("{$base}/Http/Requests/{$doc}Request.php", $this->requestStub($doc));
            $files->put("{$base}/Http/Resources/{$doc}Row.php", $this->resourceStub($doc));
            $files->put(
                "{$base}/Database/Migrations/{$context}/".date('Y_m_d_His')."_create_{$table}_table.php",
                $this->migrationStub($table)
            );
            $this->scaffoldVue($files, $key, $doc);
        }

        // 9) test
        $testDir = base_path('tests/Feature/Modules/'.$name);
        $files->ensureDirectoryExists($testDir);
        $files->put($testDir.'/'.$name.'ModuleTest.php', $this->testStub($name, $key, $doctypes));

        $this->newLine();
        $this->info("Module [{$name}] scaffolded at app/Modules/{$name}");
        $this->line('Next:');
        $this->line('  1. php artisan khattakbits:manifest            # kb-product.json update');
        $this->line('  2. php artisan tenants:seed-permissions        # naye keys har tenant mein');
        $this->line('  3. php artisan migrate                         # central (agar --central)');
        $this->line('  4. php artisan tenants:migrate                 # har tenant');
        $this->line('  5. npm run build');

        return self::SUCCESS;
    }
}
```

**Do lines jo sab se zyada value deti hain:**

```php
// menu.php stub — path STATIC hai, href RUNTIME
[
    'key'  => 'accounting.sales_invoices',
    'path' => '/accounting/sales-invoices',                    // route registration ke liye
    'href' => tenant_url(null, '/accounting/sales-invoices'),  // render ke liye
    'can'  => 'accounting.sales_invoices.view',
]
```

```php
// migration stub — cross-DB FK KABHI nahi (02 §5.5)
$table->unsignedBigInteger('created_by')->nullable()->index();
// $table->foreignId('created_by')->constrained('users');   ← tenant DB mein `users` hai hi nahi
```

### 6.4 Generated permissions — ek hi source

```php
// app/Modules/Accounting/permissions.php  (generated)
return [
    'accounting.sales_invoices.view',
    'accounting.sales_invoices.create',
    'accounting.sales_invoices.update',
    'accounting.sales_invoices.delete',
    'accounting.journal_entries.view',
    'accounting.journal_entries.create',
    'accounting.journal_entries.update',
    'accounting.journal_entries.delete',
];
```

```php
// vendor/khattakbits/core/src/Console/SeedTenantPermissions.php  (NEW)
// php artisan tenants:seed-permissions [--tenant=acme]
//
// Har tenant DB mein chalta hai (07 §2.3.1a ke mutabiq Role/Permission ka
// connection `tenant` hai). Idempotent: Permission::findOrCreate().
foreach (app(ModuleRegistry::class)->all() as $module) {
    $file = $module['path'].'/permissions.php';

    if (! is_file($file)) {
        continue;
    }

    foreach (require $file as $permission) {
        \KhattakBits\Core\Models\Permission::findOrCreate($permission, 'web');
    }
}
```

**Permission keys ka shape `04` §4.3 se match karna lazmi hai** (`{module}.{resource}.{action}`) — warna `PermissionMatrix` grid toot jata hai.

**`PermissionRegistrar` ka cache key per-tenant set karna lazmi hai** (`07` §2.3.1a). Yeh nahi kiya to tenant A ke permissions tenant B ko serve hote hain = **privilege escalation**.

### 6.5 Generated entitlement gate — 3 jagah

```php
// 1) routes.php — route level
Route::middleware(['can:accounting.sales_invoices.view', 'entitled:accounting'])
    ->group(function () {
        Route::get('/accounting/sales-invoices', [SalesInvoiceController::class, 'index'])
            ->name('accounting.sales_invoices.index');
    });
```

```php
// 2) controller — service level (route middleware bypass ho jaye to bhi)
public function store(SalesInvoiceRequest $request): RedirectResponse
{
    abort_unless(Entitlement::has('accounting.invoices'), 402, 'Plan upgrade required.');

    if ($limit = Entitlement::limit('accounting.periods')) {
        abort_if(Period::count() >= $limit, 402, 'Plan limit reached.');
    }

    // ...
}
```

```vue
<!-- 3) resources/js/Pages/Accounting/SalesInvoice/Index.vue — UX ke liye, security ke liye nahi -->
<script setup>
import { useEntitlement } from '@khattakbits/sso-client/composables/useEntitlement'

const { has, limit } = useEntitlement()
</script>

<template>
  <ListPage>
    <template #actions>
      <Button v-if="has('accounting.invoices')" :href="tenantPath('/accounting/sales-invoices/create')">
        New Invoice
      </Button>
      <UpgradePrompt v-else feature="accounting.invoices" />
    </template>
  </ListPage>
</template>
```

**Order matter karta hai:** route gate (403 se pehle) → service gate (defence in depth) → UI (sirf UX). Sirf UI par bharosa = devtools se poora product free.

### 6.6 Generated test

```php
// tests/Feature/Modules/Accounting/AccountingModuleTest.php  (generated)
namespace Tests\Feature\Modules\Accounting;

use Tests\TenantTestCase;

final class AccountingModuleTest extends TenantTestCase
{
    public function test_routes_require_tenant_context(): void
    {
        $this->actingAs($this->ownerUser());

        // {tenant} ke baghair route exist nahi karti
        $this->get('/accounting/sales-invoices')->assertNotFound();
        $this->get('/'.$this->tenant->slug.'/accounting/sales-invoices')->assertOk();
    }

    public function test_entitlement_gate_blocks_without_plan_key(): void
    {
        $this->withoutEntitlement('accounting');
        $this->actingAs($this->ownerUser());

        $this->get('/'.$this->tenant->slug.'/accounting/sales-invoices')->assertForbidden();
    }

    public function test_permissions_are_tenant_scoped(): void
    {
        $this->actingAs($this->ownerUser());

        $this->assertTrue($this->user->can('accounting.sales_invoices.view'));

        tenancy()->initialize($this->otherTenant);

        $this->assertFalse($this->user->can('accounting.sales_invoices.view'));
    }

    public function test_get_routes_do_not_write(): void
    {
        // §8 ka gate is module par bhi lagta hai — yahan sirf smoke
        $this->assertTrue(true);
    }
}
```

---

## 7. Entitlement gating — teen jagah, ek contract

### 7.1 Teeno jagah ka farq

| Jagah | Kya check hota hai | Fail par | Kyun lazmi |
|---|---|---|---|
| **Route middleware** `entitled:{module}` | `Entitlement::has()` — snapshot ka `flags` map | `403` (ya `402` upgrade prompt) | Sab se sasta gate; request controller tak pahunchti hi nahi |
| **Service / controller** | `Entitlement::has()` + `Entitlement::limit()` | `402` + JSON error | Defence in depth — naya route middleware bhool jaye to bhi data leak nahi |
| **Vue** | wahi snapshot, shared prop se | Button hide + `UpgradePrompt` | Sirf UX. **Security nahi** |

### 7.2 `Entitlement` helper ka contract

```php
// vendor/khattakbits/sso-client/src/Support/Entitlement.php
namespace KhattakBits\SsoClient\Support;

final class Entitlement
{
    public static function has(string $flag): bool
    {
        return (bool) (self::snapshot()['flags'][$flag] ?? false);
    }

    /** null = unlimited */
    public static function limit(string $key): ?int
    {
        $value = self::snapshot()['limits'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public static function quota(string $key): ?array
    {
        return self::snapshot()['quotas'][$key] ?? null;
    }

    public static function mode(): string
    {
        // missing/unknown → read_only (04 §2.4)
        return self::snapshot()['access_mode'] ?? 'read_only';
    }

    private static function snapshot(): array
    {
        return app(\KhattakBits\SsoClient\Subscriptions\ResolvesSubscription::class)->current();
    }
}
```

**`null` = unlimited, `-1` nahi.** Snapshot mein `limits` `null` rakhta hai (`04` §3.3) kyunke `-1` ko client ghalti se "1 se kam" samajh sakta hai. Blade/Vue mein `null` ko "Unlimited" render karein, `0` ko "Not included".

### 7.3 `read_only` ke saath interaction — sab se aasan bug

| Situation | Ghalat | Sahi |
|---|---|---|
| `access_mode = read_only`, user `store()` call kare | `403` (permission ka masla lagta hai) | `423 Locked` ya `402` with "Renew to continue" — **permission nahi, subscription** |
| `access_mode = read_only`, `GET` route | block | **allow** — read-only ka matlab yahi hai |
| `access_mode = locked`, `GET /reports` | block | allow (export + billing allowed) |
| `access_mode = blocked` | `403` | `404` — tenant ka wajood leak na ho |

**Yeh farq error message mein dikhna lazmi hai.** "Aap ko permission nahi hai" aur "aap ki subscription expire ho gayi" do alag cheezein hain — pehla support ticket admin ke paas jata hai, doosra billing page par.

### 7.4 Blade directive

```blade
{{-- resources/views/**/*.blade.php --}}
@entitled('accounting.invoices')
    <a href="{{ tenant_url(null, '/accounting/sales-invoices') }}">Invoices</a>
@else
    <a href="{{ tenant_url(null, '/billing/renew') }}">Upgrade to unlock Invoices</a>
@endentitled
```

---

## 8. `GET` routes read-only CI gate

### 8.1 Kyun — bypass ka woh rasta jo koi guard nahi pakadta

`08` §2.12 ka `SubscriptionGuard` `read_only` mein `GET`/`HEAD`/`OPTIONS` **allow** karta hai. Yeh design par majboor hai — read-only ka matlab yahi hai.

Magar uska natija: agar koi naya module `GET /invoices/{id}/send` jaisa route add kar de jo state badalta hai, to **grace period mein bhi woh chal jayega** aur `read_only` bypass ho jayega — bina kisi guard mein tabdeeli ke. Guard ko nahi pakadna; **route ko pakadna** hai.

### 8.2 Gate

```php
// tests/Feature/GetRoutesAreReadOnlyTest.php  (NEW — product app)
namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

final class GetRoutesAreReadOnlyTest extends TenantTestCase
{
    /**
     * Jaan boojh kar allowed — har ek ka comment lazmi hai.
     * Isay barhane se pehle sawal: "kya yeh grace mein chalna chahiye?"
     *
     * @var array<int, string>
     */
    private array $allow = [
        'logout',            // session delete — auth ka hissa, grace se pehle
        'kb.billing.return', // gateway se wapsi — read-only mein bhi chalti hai
    ];

    public function test_get_routes_do_not_write(): void
    {
        Queue::fake();

        $writes = [];

        // Dono connections — tenancy `database.default` ko `tenant` par switch karti hai
        foreach (['central', 'tenant'] as $connection) {
            DB::connection($connection)->listen(function ($query) use (&$writes, $connection) {
                if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|truncate|create)\b/i', $query->sql)) {
                    $writes[] = $connection.': '.$query->sql;
                }
            });
        }

        $this->actingAs($this->ownerUser());

        $failures = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if ($route->getName() !== null && in_array($route->getName(), $this->allow, true)) {
                continue;
            }

            $writes = [];

            try {
                $this->get('/'.ltrim($route->uri(), '/'));
            } catch (\Throwable) {
                // 403/404/500 bhi theek — hum sirf side effects dekh rahe hain
            }

            if ($writes !== []) {
                $failures[] = $route->uri().' -> '.$writes[0];
            }
        }

        $this->assertSame([], $failures, "GET routes ne data likha:/n".implode("/n", $failures));

        $this->assertSame([], Queue::pushedJobs(), 'GET route ne job dispatch kiya');
    }
}
```

### 8.3 `DB::listen` `grep` se behtar kyun

| Tareeqa | Kya miss karta hai |
|---|---|
| `grep 'Model::create'` | `DB::table()->update()`, raw SQL, `upsert()`, `firstOrCreate()`, model events, observers |
| **`DB::listen`** | Kuch nahi — jo bhi query chali, pakdi jayegi |

### 8.4 Gate ki honest limits

| Limit | Asar |
|---|---|
| Ek hi user/role se chalta hai | Doosre role ka GET route test nahi hota — `$allow` mein comment likh kar accept karein |
| Route params | `{id}` ke liye `1` bhejta hai — 404 aata hai, magar write phir bhi pakda jata hai |
| Sync jobs | `Queue::fake()` sirf queue pakadta hai; `dispatch_sync` DB write hai, woh `DB::listen` pakadta hai |
| **Destructive GET** | Test khud data corrupt kar sakta hai — is liye `RefreshDatabase` **aur** alag CI database lazmi |
| Slow | Har GET route hit hota hai — **nightly** chalayein, per-commit nahi |

```yaml
# .github/workflows/nightly.yml
name: nightly
on:
  schedule:
    - cron: '0 20 * * *'   # 01:00 PKT
jobs:
  read-only-gate:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: kb_gate
          MYSQL_ROOT_PASSWORD: secret
        ports: ['3306:3306']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: mbstring, pdo_mysql, sodium, gd, zip, intl
      - run: composer install --no-interaction --prefer-dist
      - run: php artisan test --filter=GetRoutesAreReadOnlyTest
        env:
          DB_CONNECTION: mysql
          DB_DATABASE: kb_gate
```

### 8.5 Per-commit static pre-check (sasta, adhoora)

Nightly gate ke saath ek **static** check bhi rakhein — woh commit par foran fail karta hai:

```bash
# scripts/check-get-routes.sh — per commit, 2 second
php artisan route:list --method=GET --except-vendor --json \
  | jq -r '.[] | "\(.uri) \(.action)"' \
  | grep -Ei 'send|publish|approve|verify|submit|dispatch|sync|delete|create|store|update' \
  && { echo "GET route ka naam side-effect jaisa lagta hai — GetRoutesAreReadOnlyTest dekhein"; exit 1; } \
  || true
```

Yeh heuristic hai — false positive dega (`/create` GET form hai, jo theek hai). Isliye woh **warning** hai, gate nahi. Asli gate nightly `DB::listen` wala hai.

---

## 9. Definition of done — per product

Yeh checklist product ke pehle tenant se pehle poori honi chahiye. Har item ek **test** ya **command** hai, opinion nahi.

### 9.1 Tenancy

- [ ] `MigrationSplitTest` pass — tenant DB mein `users` **nahi**, `attachments` **hai** (§2.5)
- [ ] `ConnectionPinningTest` pass — `session.connection` aur `queue.connections.database.connection` `central` par pinned (§3.5)
- [ ] `ReservedSlugCoverageTest` pass — har central root segment reserved list mein (§3.3)
- [ ] `test_url_tampering_with_tenant_a_session_returns_404` pass (`07` T2)
- [ ] `test_permission_cache_is_not_shared_between_tenants` pass (`07` T7) — **privilege escalation ka regression test**
- [ ] Har tenant route ka `parameterNames()[0] === 'tenant'` (`07` T14)

### 9.2 Identity + subscription

- [ ] `KB_CLIENT_SECRET` sirf `.env` mein — `grep -r "client_secret" resources/js/` khali
- [ ] Callback membership `GET /api/v1/users/tenants` se aati hai, URL se nahi (`08` §2.9)
- [ ] `state` session-bound hai — `StateStore::pull()` `session_id` compare karta hai (`08` §2.8.3)
- [ ] `return_to` sirf `kb_oauth_state` se, callback query string se **nahi** (`08` §2.8.6)
- [ ] Webhook envelope ka `id`/`type` `X-KB-Event-Id`/`X-KB-Event` headers se `hash_equals` cross-check (`08` §2.14)
- [ ] `KB_FAIL_MODE=closed` production `.env` mein

### 9.3 Entitlements

- [ ] Har plan ke entitlements = manifest ke features ki ginti (§4.7)
- [ ] Har gated route par `entitled:` middleware **aur** controller mein `Entitlement::has()` (§6.5)
- [ ] `read_only` mein `POST` par `423`/`402`, `403` nahi (§7.3)
- [ ] `GetRoutesAreReadOnlyTest` nightly green (§8)

### 9.4 Catalog

- [ ] `php artisan khattakbits:manifest --check` CI mein green (§4.3)
- [ ] Manifest push ke baad CP par modules/features/doctypes/reports counts match (§4.7)
- [ ] `products.domain` set — `tenant_url(absolute: true)` sahi host de

### 9.5 Quality

- [ ] `php artisan test` green, `vendor/bin/pint --test` green
- [ ] `npm run build` green — "Unable to locate file in Vite manifest" nahi
- [ ] `grep -rn "v-html" resources/js/` → **zero** (`07` §4.2 M3)
- [ ] `CSP_MODE=enforce` staging par, `report-only` nahi

### 9.6 Operations

- [ ] `09` §14 ka production checklist poora
- [ ] `php -m | grep sodium` server par
- [ ] `tenants:sweep-stuck` scheduler mein har 5 min (`07` §6.5)
- [ ] Backup script central + **saare** tenant DBs dump karta hai (`09` §12)
- [ ] `tenants:migrate` deploy pipeline mein — manual SSH nahi (`09` §11)

---

## 10. Anti-patterns

| Galti | Sahi | Kyun |
|---|---|---|
| Module ke `routes.php` mein `{tenant}` likhna | host ke `Route::prefix('{tenant}')` par chhodein | `/{tenant}/{tenant}/...` ban jata hai |
| Menu `href` mein slug hardcode | `path` static + `href` = `tenant_url()` | `href` route URI ban jaye to slug bake ho jata hai — tenant B ki 404 (`03` D3.2) |
| `constrained('users')` tenant migration mein | `unsignedBigInteger` + index | Tenant DB mein `users` table hai hi nahi (`02` §5.5) |
| `Cache::tags()` use karna | manual prefix `t:{id}:` | `CACHE_STORE=file` taggable nahi — tests pass (array store), prod 500 (`07` §1.5) |
| `session.connection` `null` chhorna | `'central'` | Tenant DB mein `sessions` table nahi — 500 |
| Membership URL se nikalna | `GET /api/v1/users/tenants` | Tenant hopping (`07` §3.6.1) |
| Entitlement sirf Vue mein check karna | route + service + UI | Devtools se poora product free |
| `KB_FAIL_MODE=open` production mein | `closed` | "CP down hai to sab allow" = subscription bypass |
| Price manifest mein bhejna | CP admin / seeder | Pricing ka do source of truth |
| `type: path` composer repo commit karna | `type: vcs` + deploy key | CI mein toota symlink (`09` §6) |
| `stancl/tenancy` CP mein install karna | sirf product apps mein | CP single-DB hai (`04` §7) |
| Laravel Octane | na chalayein | `URL::defaults` request ke baad leak — tenant A ka base URL tenant B ko (`07` R15) |

---

## 11. Timeline — pehla product

**Do numbers jo overlap karte hain, is liye jama nahi kiye:**

| Kaam | Din | Source |
|---|---|---|
| Integration layer (sso-client + identity + subscription + webhook + guard + entitlements + pages + tests + docs) | **13.5** | `08` §5 |
| Product modules, reports, plan gating, testing, UAT, go-live | **39** | `docs/04-DEVELOPMENT-GUIDE.md` §10 ka 50, minus 11 (SSO 4 + Subscription 4 + Webhooks 3 — jo `08` mein hain) |
| **Total** | **52.5** | |

**Yeh 50 se zyada kyun:** `04-DEVELOPMENT-GUIDE` §10 ne us 11 din ka estimate diya tha; `08` ne wahi kaam **13.5** din mein measure kiya (login CSRF fix, `return_to` provenance, webhook header cross-check, `users/tenants` endpoint, 25 tests). Yani purana 50 ab **52.5** hai — 2.5 din ka farq **design bugs band karne** ka hai, scope creep nahi.

**Solo dev:** ~52.5 working days ≈ **10.5 weeks**. **+20% buffer** → **12.5 weeks**.
**Do devs:** ~7 weeks (ek integration + billing, doosra modules) — `docs/04-DEVELOPMENT-GUIDE.md` §10 ka hi ratio.

**Critical path:** `make:module` v2 (§6) → pehla module → manifest sync (§4) → CP registration → SSO (§5) → entitlement gate (§6.5) → UAT.

**Agar sirf MVP chahiye** (1 module + SSO + billing): **6 weeks** possible — `docs/04-DEVELOPMENT-GUIDE.md` §10 ka hi estimate, aur woh abhi bhi valid hai.

---

## 12. Changelog

### 13 Nov 2026 — v1 (initial)

| # | Kya | Kahan | Kyun |
|---|---|---|---|
| 1 | Naya document — `docs/04-DEVELOPMENT-GUIDE.md` ka §2.3, §6, §7, §10 replace | poora | Woh path-based tenancy (`07`), package split (`03`), aur `sso-client` (`08`) se **pehle** likhi gayi thi |
| 2 | **Manifest sync** — schema, `khattakbits:manifest` command, checksum rule, CP import rules + 4 naye validation rules | §4 | `04` §6 ne shape aur diff rules diye, magar command nahi banaya, checksum ka rule nahi likha, aur 4 holes chhode (key mismatch, checksum, version regression, feature type change) |
| 3 | **`make:module` v2** — tenancy-aware, `manifest.php` + `permissions.php` + `path`/`href` menu + test | §6 | Purana generator `Database/Migrations/` mein likhta tha (tenant DB mein table hi nahi banta) aur `href` mein slug bake karta tha |
| 4 | **Migration folders** — layout, provider, 3 traps, filename immutability, split test | §2 | `03` Part D ka design; yahan product-side runbook |
| 5 | **Path-based tenancy** — route structure, middleware order, reserved slugs, `tenantUrl`, connection pinning, membership | §3 | `07` ka design; yahan naye product ke liye step-by-step |
| 6 | **Entitlement gating** — 3 jagah + `read_only` interaction | §7 | `08` §2.13 helper deta hai; "kahan lagana hai" aur "error code kya ho" yeh missing tha |
| 7 | **`GET` read-only gate** — nightly, honest limits | §8 | `05` §7.1 ka gate; yahan product app ke liye + CI wiring |
| 8 | **Definition of done** — 6 categories, har item test/command | §9 | `docs/04-DEVELOPMENT-GUIDE.md` §9 ka replacement, tenancy + entitlements + catalog ke saath |
| 9 | **Timeline 52.5 din** — 13.5 (`08`) + 39 (`04-DEV` §10 minus 11) | §11 | Purana 50 `08` ke naye numbers se match nahi karta; farq explain kiya |

**Kya nahi badla:** `docs/04-DEVELOPMENT-GUIDE.md` §1 (clone), §3 (folder/naming), §8 (starter-kit update strategy) — woh abhi bhi valid hain aur yahan repeat nahi kiye.

**Explicitly `NOT VERIFIED`:** `khattakbits:manifest` command ka behaviour (command maujood nahi — `04` §6.1), `tenants:seed-permissions` (naya), `BuildManifest` ka `--check` CI wiring, aur `make:module` v2 ka poora stub set (design hai, code nahi).

---

*Yeh playbook design + runbook hai. Har code block ke saath uska file path likha hai; jahan file maujood nahi wahan **NEW** hai. Implementation ke liye alag `IMPLEMENT` prompt chahiye.*
