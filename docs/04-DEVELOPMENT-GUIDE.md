# Development Guide — Naya SaaS Product Start Karna

**Yeh document us waqt parhein jab aap KhattakBits starter kit se naya product banane lagayen** (MizaanbitsERP, BitCart, LawBits, ...).

> **Reality check pehle:** Abhi jo repo hai usme starter kit ka **80 %** hissa maujood hai (Auth, Users, Roles, Audit, Mailer, Documents, CSP, Module system, 101 tests) — lekin **SSO client, subscription guard, entitlement layer, aur `make:module` generator nahi hai**. Neeche steps mein jo "naya" hai woh `03-INTEGRATION-GUIDE.md` se aata hai.

---

## 0. Poora lifecycle — 10 steps

```
1. Clone starter        →  2. Rename/config     →  3. Register in CP
        ↓                                               ↓
4. OAuth keys          →  5. Modules/features  →  6. First module
        ↓                                               ↓
7. Tenant testing      →  8. Plan gating       →  9. Staging
        ↓
10. Go-live
```

Har step ke saath "done" ka criteria hai. **Step skip na karein** — khaas taur par 3 aur 4 (registration + keys), kyunke baad mein retro-fit karna painful hota hai.

---

## 1. Starter kit se clone

### 1.1 Repo strategy — pehle yeh decide karein

Teen options hain. Main aap ko **Option B** recommend karta hoon.

| Option | Kaise | Faida | Nuqsan |
|---|---|---|---|
| **A. Template repo** | GitHub "Use this template" | Sabse simple, har product clean history | Starter kit updates **manually** merge karne padenge — 3 products ke baad merge hell |
| **B. Private monorepo + composer packages** ⭐ | `packages/khattakbits-core`, `packages/khattakbits-ui`; products `composer require khattakbits/core:^1.4` | Semver, `composer update`, clean diffs, ek jagah fix sab products mein | Initial extraction ~4-5 din |
| **C. Git submodule** | `git submodule add ... core` | Shared code | Submodule pinning, CI complexity, developers bhool jate hain update karna |

**Kyun B:** Aap ke paas 4+ products ka plan hai. Option A se har security fix 4 repos mein manually merge hoga — aur `app/Core/Support/` mein 14 classes hain jo constantly evolve hongi. Ek `Audit` bug fix 4 jagah repeat karna sustainable nahi.

**Lekin:** Abhi **Option A se shuru karein** (product #1), aur Option B par **product #2 se pehle** migrate karein. Wajah: aap ko pehle yeh seekhna hai ke Core mein kya actually reuse hota hai — premature extraction se galat boundaries ban jayengi. (Detail section 8 mein.)

### 1.2 Clone commands (Option A — product #1 ke liye)

```bash
# ── Naya product ────────────────────────────────────────────────────────────
git clone git@github.com:YOURUSER/khattakbits-starter.git mizaanbits-erp
cd mizaanbits-erp
git remote rename origin starter
git remote add origin git@github.com:YOURUSER/mizaanbits-erp.git
git push -u origin main

# Future starter-kit updates lane ke liye:
git fetch starter
git merge starter/main --allow-unrelated-histories
# conflicts sirf app/Core + resources/js/core mein aayenge (agar aap ne unhe chheda)
```

> **Golden rule:** Product repo mein `app/Core/**` aur `resources/js/core/**` ko **kabhi edit na karein**. Sirf `app/Modules/**`, `app/Kb/**`, `resources/js/Pages/**`, `config/`, `.env` chhuein. Isse `git merge starter/main` clean rehta hai. Agar Core mein change chahiye — **starter repo mein karein**, phir merge karein.

---

## 2. Rename & configure

### 2.1 Identity

```bash
# composer.json
#   "name": "khattakbits/mizaanbits-erp"
#   "description": "Mizaanbits ERP — accounting & inventory for SMEs"

# package.json — name field
# .env.example — APP_NAME, APP_URL
# config/app.php — 'name' => env('APP_NAME', 'Mizaanbits ERP')
```

**Files jo product-specific hain (badalna hai):**

| File | Kya badlein |
|---|---|
| `composer.json` | `name`, `description` |
| `package.json` | `name` |
| `.env.example` | `APP_NAME`, `APP_URL`, `SESSION_COOKIE`, `REDIS_PREFIX`, `KB_*` |
| `config/khattakbits.php` | `product_key`, `role_map` |
| `app/Modules/Home/menu.php` | `label`, `sections` (dashboard widgets) |
| `resources/js/Pages/Home/Dashboard.vue` | Branding, KPIs |
| `resources/js/core/login3d/coverArt.js` | **Login book ka cover** — product ka naam/logo |
| `public/favicon.ico`, `public/apple-touch-icon.png` | Icons |
| `config/attachments.php` | Product ke doctypes ke attachment types |

### 2.2 Modules jo remove karne hain

Starter kit mein demo modules hain — product mein **delete** karein:

```bash
rm -rf app/Modules/AccountingDemo     # shell hai, kaam ka nahi
rm -rf app/Modules/Examples           # reference ke liye rakhein? NAHI — product mein na jaye
rm -rf resources/js/Pages/Examples
```

> **`Examples` module ko starter repo mein rakhein** (reference ke liye), magar product clone mein pehle din hata dein. Warna `examples.entries.*` permissions seed hongi aur `examples/forbidden` route production mein chala jayega (bug B-2).

`database/seeders/` mein se demo seeders bhi hata dein.

### 2.3 `make:module` command — pehle yeh banayen

Abhi `php artisan list | grep module` → kuch nahi. Yeh command **starter kit ka hissa hona chahiye**, product ka nahi. Ek dafa likhein, har module ke 30 minute bachayen.

`app/Core/Console/MakeModule.php`:

```php
<?php

namespace App\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeModule extends Command
{
    protected $signature = 'make:module
                            {name : Module name, e.g. Accounting}
                            {--key= : Module key (default: slug of name)}
                            {--with-doctype= : Comma separated doctypes, e.g. SalesInvoice,PurchaseInvoice}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a new module in app/Modules with menu, routes, controller, model, migration and Vue pages';

    public function handle(Filesystem $files): int
    {
        $name = Str::studly($this->argument('name'));
        $key  = $this->option('key') ?: Str::slug($name);
        $base = app_path("Modules/{$name}");

        if (is_dir($base) && ! $this->option('force')) {
            $this->error("Module [{$name}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $doctypes = collect(explode(',', (string) $this->option('with-doctype')))
            ->map(fn ($d) => trim($d))
            ->filter()
            ->map(fn ($d) => Str::studly($d))
            ->values();

        // ── Directories ─────────────────────────────────────────────────────
        foreach ([
            '',
            'Database/Migrations',
            'Http/Controllers',
            'Http/Requests',
            'Http/Resources',
            'Models',
        ] as $dir) {
            $files->ensureDirectoryExists($base.($dir ? '/'.$dir : ''));
        }

        // ── menu.php ────────────────────────────────────────────────────────
        $sections = $doctypes->map(fn ($doc) => [
            'title' => Str::headline($doc).'s',
            'items' => [[
                'label' => Str::headline($doc),
                'href'  => "/{$key}/".Str::slug(Str::snake($doc)),
                'can'   => "{$key}.".Str::snake($doc).'.view',
                'group' => null,
            ]],
        ])->all();

        if (! $sections) {
            $sections = [[
                'title' => 'Overview',
                'items' => [[
                    'label' => Str::headline($name),
                    'href'  => "/{$key}",
                    'can'   => null,
                    'group' => null,
                ]],
            ]];
        }

        $files->put($base.'/menu.php', $this->menuStub($key, $name, $sections, $doctypes));

        // ── routes.php ──────────────────────────────────────────────────────
        $files->put($base.'/routes.php', $this->routesStub($key, $doctypes));

        // ── Per doctype: model, controller, request, resource, migration ────
        foreach ($doctypes as $doc) {
            $table = $key.'_'.Str::snake(Str::pluralStudly($doc));

            $files->put(
                "{$base}/Models/{$doc}.php",
                $this->modelStub($doc, $table)
            );
            $files->put(
                "{$base}/Http/Controllers/{$doc}Controller.php",
                $this->controllerStub($key, $doc)
            );
            $files->put(
                "{$base}/Http/Requests/{$doc}Request.php",
                $this->requestStub($doc)
            );
            $files->put(
                "{$base}/Database/Migrations/".date('Y_m_d_His')."_create_{$table}_table.php",
                $this->migrationStub($table)
            );

            $this->scaffoldVue($files, $key, $doc);
        }

        // ── Permission seed note ────────────────────────────────────────────
        $this->newLine();
        $this->info("Module [{$name}] scaffolded at app/Modules/{$name}");
        $this->line('Next:');
        $this->line('  1. php artisan migrate');
        $this->line('  2. Permissions add karein: database/seeders/PermissionSeeder.php');
        $this->line("     " . $doctypes->map(fn ($d) => "{$key}.".Str::snake($d).'.{view,create,update,delete}')->implode(', '));
        $this->line('  3. npm run dev');

        return self::SUCCESS;
    }

    private function menuStub(string $key, string $name, array $sections, $doctypes): string
    {
        $export = var_export($sections, true);

        return <<<PHP
<?php

return [
    'key'         => '{$key}',
    'label'       => '{$name}',
    'prefix'      => '/{$key}',
    'order'       => 60,
    'icon'        => 'folder',
    'permissions' => [],
    'sections'    => {$export},
];
PHP;
    }

    private function routesStub(string $key, $doctypes): string
    {
        $body = $doctypes->map(function ($doc) use ($key) {
            $snake = Str::snake($doc);
            $uri   = Str::slug($snake);
            $ctrl  = "{$doc}Controller::class";

            return <<<PHP
    Route::prefix('{$uri}')->group(function () {
        Route::middleware('can:{$key}.{$snake}.view')->group(function () {
            Route::get('/', [{$ctrl}, 'index']);
            Route::get('data', [{$ctrl}, 'data']);
            Route::get('{id}', [{$ctrl}, 'show'])->whereNumber('id');
        });
        Route::middleware('can:{$key}.{$snake}.create')->group(function () {
            Route::get('create', [{$ctrl}, 'create']);
            Route::post('/', [{$ctrl}, 'store']);
        });
        Route::put('{id}', [{$ctrl}, 'update'])->whereNumber('id')
            ->middleware('can:{$key}.{$snake}.update');
        Route::delete('{id}', [{$ctrl}, 'destroy'])->whereNumber('id')
            ->middleware('can:{$key}.{$snake}.delete');
    });
PHP;
        })->implode("\n\n");

        $uses = $doctypes->map(fn ($d) => "use App\\Modules\\{$key}\\Http\\Controllers\\{$d}Controller;")->implode("\n");

        return <<<PHP
<?php

{$uses}
use Illuminate\\Support\\Facades\\Route;

Route::prefix('{$key}')->group(function () {
{$body}
});
PHP;
    }

    private function modelStub(string $doc, string $table): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{MODULE}\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

class {$doc} extends Model
{
    use SoftDeletes;

    protected \$table = '{$table}';

    protected \$guarded = ['id'];
}
PHP;
    }

    private function controllerStub(string $key, string $doc): string
    {
        $snake = Str::snake($doc);
        $var   = Str::camel($doc);

        return <<<PHP
<?php

namespace App\\Modules\\{MODULE}\\Http\\Controllers;

use App\\Core\\Support\\Audit;
use App\\Http\\Controllers\\Controller;
use App\\Modules\\{MODULE}\\Http\\Requests\\{$doc}Request;
use App\\Modules\\{MODULE}\\Models\\{$doc};
use Illuminate\\Http\\Request;
use Inertia\\Inertia;

class {$doc}Controller extends Controller
{
    public function index()
    {
        return Inertia::render('{MODULE}/{$doc}/Index');
    }

    public function data(Request \$request)
    {
        return response()->json(
            {$doc}::query()->latest('id')->paginate(20)
        );
    }

    public function create()
    {
        return Inertia::render('{MODULE}/{$doc}/Form', ['{$var}' => null]);
    }

    public function store({$doc}Request \$request)
    {
        \${$var} = {$doc}::create(\$request->validated());
        Audit::log('{$key}.{$snake}.created', \${$var}, (string) \${$var}->getKey());

        return redirect("/{$key}/".str_replace('_', '-', '{$snake}')."/{\${$var}->id}")
            ->with('success', 'Saved.');
    }

    public function show({$doc} \${$var})
    {
        return Inertia::render('{MODULE}/{$doc}/Show', ['{$var}' => \${$var}]);
    }

    public function update({$doc}Request \$request, {$doc} \${$var})
    {
        \${$var}->update(\$request->validated());
        Audit::log('{$key}.{$snake}.updated', \${$var}, (string) \${$var}->getKey());

        return back()->with('success', 'Updated.');
    }

    public function destroy({$doc} \${$var})
    {
        \${$var}->delete();
        Audit::log('{$key}.{$snake}.deleted', \${$var}, (string) \${$var}->getKey());

        return back()->with('success', 'Deleted.');
    }
}
PHP;
    }

    private function requestStub(string $doc): string
    {
        return <<<PHP
<?php

namespace App\\Modules\\{MODULE}\\Http\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class {$doc}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // route middleware already checks 'can:'
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
        ];
    }
}
PHP;
    }

    private function migrationStub(string $table): string
    {
        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            // TODO: tenant_id (multi-tenancy aane par)
            \$table->string('title', 190);
            \$table->unsignedTinyInteger('docstatus')->default(0);   // 0 draft, 1 submitted, 2 cancelled
            \$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            \$table->timestamps();
            \$table->softDeletes();

            \$table->index(['docstatus', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};
PHP;
    }

    private function scaffoldVue(Filesystem $files, string $key, string $doc): void
    {
        $dir = resource_path("js/Pages/".Str::studly($key)."/{$doc}");
        $files->ensureDirectoryExists($dir);

        $files->put("{$dir}/Index.vue", <<<VUE
<script setup>
import ListPage from '@core/layouts/ListPage.vue'
import { router } from '@inertiajs/vue3'
</script>

<template>
  <ListPage title="{$doc}" subtitle="TODO: description">
    <template #actions>
      <button class="btn" @click="router.visit('/{$key}/".toLowerCase()")">New</button>
    </template>
  </ListPage>
</template>
VUE);
    }
}
```

Register in `CoreServiceProvider::boot()`:

```php
if ($this->app->runningInConsole()) {
    $this->commands([
        CreateAdmin::class,
        PurgeBin::class,
        PruneEmails::class,
        \App\Core\Console\MakeModule::class,   // ← add
    ]);
}
```

**Usage:**

```bash
php artisan make:module Accounting --key=accounting \
  --with-doctype=SalesInvoice,PurchaseInvoice,JournalEntry

php artisan make:module Inventory --with-doctype=StockEntry,Item
```

> **Note:** Stub ka `{MODULE}` placeholder `handle()` mein `str_replace` se replace karein (`$name` se) — neeche wala code simplicity ke liye literal rakha hai. Production version mein `str_replace('{MODULE}', $name, $stub)` lazmi.

---

## 3. Folder structure & naming conventions

### 3.1 Backend

```
app/
├── Core/                          ← STARTER (shared) — product mein edit nahi
├── Kb/                            ← STARTER (shared) — SSO/subscription client
├── Modules/
│   └── Accounting/                ← PRODUCT
│       ├── menu.php               ← nav + permissions declaration
│       ├── routes.php             ← auto-loaded by routes/web.php
│       ├── Database/Migrations/   ← auto-loaded by CoreServiceProvider
│       ├── Http/
│       │   ├── Controllers/
│       │   ├── Requests/
│       │   └── Resources/         ← API row transformers
│       ├── Models/
│       ├── Services/              ← business logic (fat controllers se bachein)
│       ├── Policies/
│       └── Jobs/
└── ControlPlane/                  ← CONTROL-PLANE ONLY (product mein nahi hota)
    ├── Models/Product.php, Plan.php, Subscription.php
    ├── Http/Controllers/Admin/
    ├── Http/Controllers/Api/
    └── Services/OAuth/, Billing/, Webhooks/
```

### 3.2 Frontend

```
resources/js/
├── core/                          ← STARTER — layouts, ui, composables, login3d
│   ├── layouts/                   AppShell, ListPage, FormPage, ReportPage, SettingsPage
│   ├── ui/                        Button, Input, Table, Modal, ...
│   └── composables/               useServerList, useCan, useEntitlement, ...
└── Pages/
    └── Accounting/                ← PRODUCT (module ke naam se folder)
        ├── SalesInvoice/
        │   ├── Index.vue
        │   ├── Form.vue
        │   └── Show.vue
        └── JournalEntry/
```

> **Rule:** `Pages/Settings/*` (starter) ko chhod kar, naye pages **`Pages/<Module>/`** mein jayen. Abhi ka flat structure (`Pages/Settings/...` 12 folders) chhota hai magar 5 modules ke baad phat jayega.

### 3.3 Naming conventions (enforce karein)

| Cheez | Convention | Example |
|---|---|---|
| Module key | lowercase, single word | `accounting`, `inventory`, `crm` |
| Module folder | StudlyCase | `app/Modules/Accounting` |
| Doctype key | snake_case | `sales_invoice` |
| Permission | `<module>.<doctype>.<action>` | `accounting.sales_invoice.view` |
| Route prefix | `/<module-key>/<doctype-slug>` | `/accounting/sales-invoice` |
| Controller | `<Doctype>Controller` | `SalesInvoiceController` |
| Model | `<Doctype>` (StudlyCase) | `SalesInvoice` |
| **Table** | `<module_key>_<doctype_plural>` | `accounting_sales_invoices` |
| Migration | module ke andar, timestamped | `2026_11_15_100000_create_accounting_sales_invoices_table.php` |
| Vue page | `Pages/<Module>/<Doctype>/<Action>.vue` | `Pages/Accounting/SalesInvoice/Index.vue` |
| Audit event | `<module>.<doctype>.<past_tense>` | `accounting.sales_invoice.submitted` |
| Entitlement key | `module.<key>` ya `limit.<thing>` | `module.accounting`, `limit.users` |
| Job | `<Verb><Noun>Job` ya action | `SyncEntitlements`, `SendRenewalReminder` |

**Table prefix kyun:** Multi-tenancy single-DB mein aayegi, aur 4 products ek hi Postgres server par honge (alag DBs, magar backup/analytics ke liye naming clear hona chahiye). `sales_invoices` 3 products mein collide karega — `accounting_sales_invoices` nahi.

---

## 4. Registration in KhattakBits (control plane)

Yeh control plane ke admin panel se hota hai (jab bana ho). Abhi ke liye `php artisan tinker` ya seeder:

```bash
php artisan tinker --execute '
$product = App\ControlPlane\Models\Product::create([
    "key" => "mizaanbitserp",
    "name" => "Mizaanbits ERP",
    "domain" => "mizaanbitserp.com",
    "status" => "active",
]);

foreach (["accounting","inventory","crm"] as $m) {
    $product->modules()->create(["module_key" => $m, "is_enabled" => true]);
}

$client = $product->oauthClient()->create([
    "client_id" => "kb_live_".Str::random(24),
    "client_secret_hash" => Hash::make($secret = Str::random(48)),
    "redirect_uris" => ["https://mizaanbitserp.com/auth/kb/callback"],
    "scopes" => ["openid","profile","email","subscription","entitlements","tenant","roles"],
    "webhook_url" => "https://mizaanbitserp.com/webhooks/khattakbits",
    "signing_secret" => $signing = Str::random(48),
]);

echo "client_id: {$client->client_id}\nclient_secret: {$secret}\nsigning_secret: {$signing}\n";
'
```

**Output ko password manager mein save karein** — `client_secret` dobara nahi dikhega.

---

## 5. Modules / features / doctypes / reports define karna

Do jagah define hote hain — **dono zaroori**:

| Kahan | Kya | Kis liye |
|---|---|---|
| **KhattakBits** (DB) | modules, features, doctypes, reports, plans, entitlements | Billing, gating, catalog, admin UI |
| **Product app** (code) | `app/Modules/*/menu.php`, migrations, permissions | Actual implementation |

**Inhe sync kaise rakhein:** Control plane ka `product_doctypes` sirf **metadata** hai (naam, label, module) — billing aur gating ke liye. Product app ka asli doctype `menu.php` + migration + model hai. Do source of truth **nahi** hain — control plane "kya becha ja raha hai" jaanta hai, product "kya bana hua hai" jaanta hai. Naya doctype banate waqt **dono jagah** add karein.

**Automation (P2):** `php artisan kb:sync-catalog` — product apne `menu.php` se doctypes/reports nikaal kar `POST /api/v1/products/{key}/catalog` bheje. Tab control plane hamesha product ke mutabiq rahega. Abhi manual hai.

---

## 6. Pehla module — step by step

```bash
# 1. Scaffold
php artisan make:module Accounting --key=accounting --with-doctype=SalesInvoice

# 2. Migration
php artisan migrate

# 3. Permissions — database/seeders/PermissionSeeder.php
#    'accounting.sales_invoice.view', '.create', '.update', '.delete'
php artisan db:seed --class=PermissionSeeder

# 4. menu.php check karein (auto-generated)
cat app/Modules/Accounting/menu.php

# 5. Route verify
php artisan route:list --path=accounting

# 6. Frontend
npm run dev
```

### 6.1 `menu.php` ka structure (samajhna zaroori)

```php
return [
    'key'         => 'accounting',
    'label'       => 'Accounting',
    'prefix'      => '/accounting',
    'order'       => 60,                 // sidebar mein position
    'icon'        => 'book',
    'permissions' => [],                 // module-level extra permissions
    'sections'    => [
        [
            'title' => 'Sales',
            'items' => [
                [
                    'label' => 'Sales Invoice',
                    'href'  => '/accounting/sales-invoice',
                    'can'   => 'accounting.sales_invoice.view',   // null = har login user
                    'group' => null,
                ],
            ],
        ],
    ],
];
```

`MenuBuilder` isse nav banata hai, `PermissionCatalog` isse labels nikaalta hai, `DocumentRegistry` isse doctypes padhta hai. **Yeh file module ka contract hai** — iske baghair module invisible hai.

### 6.2 Plan gating — 3 jagah lagana hota hai

```php
// 1. Route level — poora module band
Route::middleware(['auth', 'kb.entitlement:module.accounting'])->group(function () {
    // accounting routes
});

// 2. Controller level — specific feature
abort_unless(app(Entitlement::class)->has('feature.multi_currency'), 403);

// 3. UI level — link hide
// menu.php mein:
'can' => 'accounting.sales_invoice.view',   // permission
// + Vue mein:
<Link v-if="can('module.accounting')" href="/accounting">Accounting</Link>
```

**Entitlement middleware** (naya, `app/Kb/Http/Middleware/EnsureEntitlement.php`):

```php
<?php

namespace App\Kb\Http\Middleware;

use App\Kb\Entitlement;
use Closure;
use Illuminate\Http\Request;

final class EnsureEntitlement
{
    public function __construct(private Entitlement $entitlements) {}

    public function handle(Request $request, Closure $next, string $key)
    {
        if (! $this->entitlements->has($key)) {
            abort(403, "Yeh feature aap ke plan mein shamil nahi ({$key}).");
        }

        return $next($request);
    }
}
```

Register alias in `bootstrap/app.php`:

```php
$middleware->alias([
    'kb.entitlement'   => \App\Kb\Http\Middleware\EnsureEntitlement::class,
    'kb.subscription'  => \App\Http\Middleware\EnsureSubscriptionActive::class,
    'kb.session'       => \App\Http\Middleware\EnsureKbSession::class,
]);
```

---

## 7. Tenant testing

```bash
# ── Local tenant setup ──────────────────────────────────────────────────────
# hosts file (Windows: C:\Windows\System32\drivers\etc\hosts)
127.0.0.1  mizaanbitserp.test
127.0.0.1  acme.mizaanbitserp.test
127.0.0.1  beta.mizaanbitserp.test

# .env
APP_URL=http://mizaanbitserp.test
SESSION_DOMAIN=.mizaanbitserp.test
```

**Test matrix:**

| Test | Kaise | Expected |
|---|---|---|
| Tenant A ka data Tenant B ko na dikhe | `acme.` par login, `beta.` par same user | Sirf apna tenant |
| Subscription expired | tinker: `$sub->update(['status' => 'expired'])` | `/billing/expired` |
| Grace read-only | `status=grace`, `grace_until=+3d` | GET chalta, POST block |
| Module gating | `kb_entitlements` mein `module.crm => false` | CRM route 403, nav mein hidden |
| Quota | `limit.invoices_per_month = 5`, 6 invoices banao | 6th par 422 |
| User disable | KhattakBits admin se disable | Product mein **turant** logout |
| Token revoke | `kb_token_version` bump | Next request 401 |
| Control plane down | `KB_BASE_URL` galat karein | 72h tak snapshot se chalta, phir read-only |
| Webhook replay | Same `event_id` dobara bhejein | 200 Duplicate, dobara process nahi |

**Automated:** `tests/Feature/Kb/*` — integration guide section 14 ka code use karein. Har row ka ek test hona chahiye.

---

## 8. Starter-kit updates future products mein kaise layen

### 8.1 Option A (abhi) — git merge

```bash
cd mizaanbits-erp
git fetch starter
git log --oneline HEAD..starter/main          # kya naya hai
git merge starter/main

# Conflicts sirf yahan aane chahiye:
#   app/Core/**            ← aap ne edit kiya to conflict (mat karein)
#   resources/js/core/**   ← same
#   routes/web.php         ← product ne routes add kiye to hamesha conflict
#   composer.json/lock     ← dependency conflict
```

**`routes/web.php` conflict ka permanent hal:** product apni routes **module ke `routes.php` mein** rakhe, `web.php` ko chhue hi na. SSO routes bhi `app/Kb/routes.php` mein rakhein aur `web.php` mein ek line:

```php
// routes/web.php — sirf ek line add karein, merge-friendly
require app_path('Kb/routes.php');
```

### 8.2 Option B (recommend, product #2 se pehle) — composer packages

**Target structure:**

```
khattakbits-platform/                  ← private monorepo
├── packages/
│   ├── core/                          ← composer package: khattakbits/core
│   │   ├── composer.json              ("name": "khattakbits/core")
│   │   └── src/                       ← app/Core ka content, namespace KhattakBits\Core
│   ├── ui/                            ← composer package: khattakbits/ui (Vue components)
│   │   ├── composer.json
│   │   └── resources/js/
│   └── kb-client/                     ← composer package: khattakbits/client (SSO + subscription)
│       └── src/                       ← app/Kb ka content, namespace KhattakBits\Client
├── apps/
│   ├── khattakbits/                   ← control plane (uses all 3 packages)
│   └── mizaanbits-erp/                ← product (uses core + ui + client)
└── composer.json                      ← path repositories for local dev
```

**Root `composer.json`:**

```json
{
    "repositories": [
        {"type": "path", "url": "packages/*", "options": {"symlink": true}}
    ]
}
```

**Product `composer.json`:**

```json
{
    "repositories": [
        {"type": "vcs", "url": "git@github.com:YOURUSER/khattakbits-platform.git"}
    ],
    "require": {
        "khattakbits/core":   "^1.4",
        "khattakbits/client": "^1.2",
        "khattakbits/ui":     "^1.1"
    }
}
```

**Update flow:**

```bash
# packages/core mein fix
cd packages/core && git commit -m "fix(audit): log failed password change"
git tag v1.4.2 && git push --tags

# har product mein
composer update khattakbits/core --with-dependencies
php artisan migrate
npm run build
```

**Version policy:**

| Change | Version bump | Product ko kya karna |
|---|---|---|
| Bug fix | `1.4.1` → `1.4.2` | `composer update` |
| Naya feature (backwards compatible) | `1.4` → `1.5` | `composer update`, naye config keys add |
| Breaking change (namespace, config rename) | `1.x` → `2.0` | Manual migration + upgrade guide |

**Migration cost (ek dafa):** `App\Core\` → `KhattakBits\Core\` ka find/replace. `app/Core` mein 14 support classes + 8 models + 5 controllers hain — ~1 din ka kaam, plus `CoreServiceProvider` ko package ka service provider banana (auto-discovery se register hoga).

**Kab karein:** Product #2 se pehle. Product #1 (MizaanbitsERP) Option A se chala lein — isse aap ko pata chalega Core mein kya actually reuse hota hai aur kya product-specific hai.

### 8.3 Kya "core" hai vs "control-plane only" vs "product-specific"

| Layer | Contents | Kaun use karta hai |
|---|---|---|
| **`khattakbits/core`** (shared) | Auth, Users, Roles, `Authority`, `PermissionCatalog`, `Audit`, `Mailer`, `EmailTemplates`, `DocumentRegistry`, `IsDocument`, Recycle Bin, `SecurityHeaders`, `SettingsStore`, `TableQuery`, `MenuBuilder`, `ModuleRegistry`, layouts | Control plane **aur** har product |
| **`khattakbits/client`** (shared) | `KbOAuth`, `KbClient`, `Entitlement`, `EnsureKbSession`, `EnsureSubscriptionActive`, `KbWebhookController`, `TenantProvisioner` | **Sirf products** (control plane khud client nahi) |
| **`khattakbits/ui`** (shared) | `resources/js/core/**` — layouts, ui primitives, composables, `login3d` | Control plane + products |
| **Control-plane only** | `Product`, `Plan`, `Subscription`, `Invoice`, `Payment`, `OauthClient`, `Tenant`, `WebhookDelivery`, Passport config, `/admin/*`, `/api/v1/*`, catalog pages | **Sirf** `apps/khattakbits` |
| **Product-specific** | `app/Modules/*`, `resources/js/Pages/<Module>/*`, module migrations, module permissions, branding (`coverArt.js`, favicon), `.env` | **Sirf** woh product |

**Yeh boundary aaj se respect karein** — chahe code ek hi repo mein ho. Warna extraction ke waqt pata chalega ke `Subscription` model 4 jagah import ho raha hai.

---

## 9. Definition of Done — per product

Har product go-live se pehle **yeh checklist** pass kare:

### Functional

- [ ] Catalog par product listed hai, plan pricing dikh rahi hai
- [ ] Purchase → subscription create hoti hai control plane mein
- [ ] Purchase ke baad product par redirect hota hai (SSO loop)
- [ ] 3D login kaam karta hai (Chrome, Firefox, Safari, mobile)
- [ ] WebGL off hone par HTML fallback (blank screen nahi)
- [ ] Dashboard load hota hai, entitlements ke mutabiq modules dikhte hain
- [ ] Expired subscription par `/billing/expired` screen
- [ ] Renewal → checkout → return → unlock
- [ ] Grace period mein read-only
- [ ] Disabled user turant logout hota hai (webhook)
- [ ] Tenant provisioning + tenant switch (agar multi-tenant)

### Security

- [ ] `state` verification SSO callback par
- [ ] PKCE `S256` use ho raha hai (`plain` nahi)
- [ ] Webhook HMAC verify + 5 min replay window + idempotency
- [ ] `client_secret` sirf server-side (Vue bundle mein nahi)
- [ ] CSP `enforce` mode, zero console violations
- [ ] Attachment IDOR fix (bug B-1) — product mein bhi apply
- [ ] `examples/forbidden` route hata hua
- [ ] Rate limiting: login, webhook, API
- [ ] Audit log mein login/logout/data changes
- [ ] `.env` git mein nahi, permissions `640`

### Quality

- [ ] Tests: SSO callback, webhook signature, entitlement gate, subscription guard, expiry flow
- [ ] `php artisan test` green
- [ ] `./vendor/bin/pint --test` green
- [ ] Zero N+1 (lazy loading violations local par zero)
- [ ] Responsive: 360px, 768px, 1440px
- [ ] Keyboard navigation + focus rings
- [ ] Empty states har list par
- [ ] Error pages (403/404/500) branded

### Operations

- [ ] `.env.example` mein saare `KB_*` keys documented
- [ ] Migrations idempotent (dobara chalane par fail na hon)
- [ ] Queue worker `webhooks` queue ke saath supervisor mein
- [ ] Scheduler: `kb:refresh-entitlements`, `kb:retry-failed-webhooks`
- [ ] Backup + restore test
- [ ] Uptime monitor `/up` par
- [ ] Sentry DSN set
- [ ] Deploy + rollback ek dafa test
- [ ] `docs/` mein product ka integration note

### Commercial

- [ ] Har plan ke entitlements define hue
- [ ] Quota limits enforced (test kiya)
- [ ] Upgrade/downgrade path kaam karta hai
- [ ] Invoice/receipt email jaata hai (`EmailTemplates` se)
- [ ] Trial period (agar hai) sahi expire hota hai

---

## 10. Timeline — pehla product (MizaanbitsERP)

**Assumption:** 1 senior Laravel dev, full-time, control plane **already built** (P0 complete). Agar control plane nahi bana to +5 weeks.

| Phase | Kaam | Din |
|---|---|---|
| **Setup** | Clone, rename, config, demo modules remove, `make:module` command | 2 |
| **SSO** | Migrations, `KbOAuth`, callback, `EnsureKbSession`, tenant mapping | 4 |
| **Subscription** | `KbClient`, `Entitlement`, guard middleware, expiry flow, billing screens | 4 |
| **Webhooks** | Controller, HMAC, idempotency, jobs, retry | 3 |
| **Module 1** (Accounting core) | Sales Invoice: menu, migration, model, controller, 3 Vue pages, permissions | 6 |
| **Module 1** (Accounting rest) | Purchase Invoice, Journal Entry, Chart of Accounts | 8 |
| **Module 2** (Inventory basic) | Items, Stock Entry, ledger | 6 |
| **Reports** | Trial Balance, Stock Ledger (server-side aggregation + `ReportPage`) | 5 |
| **Plan gating** | Entitlement checks har route/page par, upgrade prompts | 2 |
| **Testing** | Feature tests, tenant matrix, security | 4 |
| **Staging + UAT** | Deploy, SSL, seed demo data, bug fixes | 4 |
| **Go-live** | Production deploy, monitoring, backups, smoke test | 2 |
| **TOTAL** | | **~50 working days ≈ 10 weeks** |

**Realistic buffer:** +20 % → **12 weeks** for a genuinely usable first product. Agar aap sirf "1 module + SSO + billing" ka MVP chahte hain to **6 weeks** possible hai.

**Agar 2 devs:** ~7 weeks (parallel: ek SSO+billing, doosra modules).

---

## 11. Anti-patterns — yeh galtiyan na karein

| ❌ Galti | ✅ Sahi tareeqa |
|---|---|
| `app/Core` product mein edit karna | Starter repo mein edit, phir merge/update |
| Billing logic product app mein likhna | Sab control plane mein; product sirf resolve call kare |
| `Subscription` model product mein banana | `kb_subscriptions` sirf **snapshot** hai; source of truth control plane |
| Permissions `product_id` ke baghair | Product-scoped roles (P1-4) — abhi global hain, jaldi fix karein |
| Entitlements sirf frontend par check karna | **Hamesha** server-side bhi (`EnsureEntitlement`) |
| `client_secret` Vue bundle mein | Sirf server-side; SPA ko backend proxy se |
| Webhook handler mein synchronous kaam | 202 return karein, `HandleKbWebhook` job dispatch karein |
| Same `event_id` dobara process karna | `insertOrIgnore` + unique index |
| `web.php` mein product routes add karna | Module ke `routes.php` mein |
| Table name `sales_invoices` | `accounting_sales_invoices` (module prefix) |
| `tenant_id` baad mein add karna | Migration mein **abhi** nullable column rakhein |
| Tests baad mein likhna | Har SSO/webhook/guard ka test saath likhein — yeh security-critical hai |
