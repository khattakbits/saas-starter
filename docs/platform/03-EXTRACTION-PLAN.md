# 03 — Extraction Plan (Part A executed · Part B planned)

**Date:** 12 Nov 2026
**Repo:** `E:/LaraProjects/saas-starter` (branch `master`)
**Depends on:** `docs/platform/02-ARCHITECTURE-DECISIONS.md` (§0 D1–D5, §5.5 Step 0)
**Rule:** Part A code hai (done). Part B **sirf plan** hai — is file mein koi package extract nahi hua.

---

# PART A — Implemented

## A1 · B-1 attachment IDOR fix

**File:** `app/Core/Http/Controllers/AttachmentController.php`

Pehle `show()` / `destroy()` sirf **type-level** permission check karte the (`$this->allow(...)`). Natija: jise `examples.entries.view` tha woh `/attachments/{id}` par id enumerate kar ke **kisi bhi** record ki file download ya delete kar sakta tha — chahe woh record uska ho hi na. `index()` / `store()` par `ensureRecord()` chalta tha, `show()` / `destroy()` par nahi.

**Ab har direct access 3 layers se guzarta hai:**

| Layer | Kya check hota hai | Fail par |
|---|---|---|
| 1. Type permission | `view` / `update` / `delete` key config mein maujood ho aur user ke paas woh permission ho | 403 |
| 2. Parent record | `attachable_type` ka `model` resolve ho kar `attachable_id` maujood ho | 404 |
| 3. Record authorization | `authorize` callback, warna `scope => owner` par owner ya `manage` permission | 403 |

`authorizeRecord()` naya private method hai (same controller) jo `index()`, `store()`, `show()`, `destroy()` — chaaron mein lagta hai. `ensureRecord()` hata diya (uska kaam ab layer 2 karta hai).

**Type config ka naya contract** (`config/attachments.php`):

```php
'demo_entry' => [
    'model'        => DemoEntry::class,
    'view'         => 'examples.entries.view',
    'update'       => 'examples.entries.update',
    'delete'       => 'examples.entries.update',
    'scope'        => 'owner',          // 'type' (default) | 'owner'
    'owner_column' => 'created_by',     // default 'created_by'
    'manage'       => 'examples.entries.update',  // fallback: 'update'
    // 'authorize' => fn (User $u, $record, string $action) => bool,  // optional, sab se pehle
],
```

- `scope => 'type'` → purana behaviour (shared ledgers ke liye theek).
- `scope => 'owner'` → sirf owner ya manager. **`demo_entry` par on kar diya** — yeh reference doctype hai, is liye default-secure dikhna chahiye. Agar aap chahte hain ke viewer bhi doosron ke record ki attachment dekh sake to `config/attachments.php` se `scope` line hata dein (one-line revert).
- `authorize` closure sab se pehle chalta hai — product apni policy yahan plug karega (multi-tenant mein `tenant_id` check isi jagah aayega).

**Tests:** `tests/Feature/AttachmentAccessTest.php` (8 tests, 17 assertions)

| Test | Kya prove karta hai |
|---|---|
| `intruder_cannot_download_another_users_attachment` | view permission ke bawajood doosre ka record → 403 |
| `intruder_cannot_delete_another_users_attachment` | delete → 403, DB row + file intact |
| `intruder_cannot_list_another_users_record_attachments` | `index` bhi leak nahi karta (names/sizes) |
| `owner_can_download_own_attachment` | owner → 200 |
| `owner_with_delete_permission_can_delete_own_attachment` | owner + delete perm → 204, file gone |
| `manager_can_read_and_delete_another_users_attachment` | manage permission wala user cross-record access kar sakta hai |
| `attachment_whose_parent_record_is_gone_is_404` | dangling `attachable_id` → 404 (purane IDOR ka asli vector) |
| `authorize_callback_overrides_the_scope_rule` | custom policy owner ko bhi block kar sakti hai |

**Residual risk (honest):** `scope => 'type'` wale doctypes par record-level read restriction **nahi** hai — kyunke app mein record-level ACL maujood hi nahi. Woh tenancy/policies ke saath aayega; tab `authorize` closure mein `tenant_id` check lag jayega.

## A2 · B-2 forbidden demo route

**File:** `app/Modules/Examples/routes.php`

```php
if (app()->environment(['local', 'testing'])) {
    Route::get('forbidden', fn () => abort(403));
}
```

Verified:

```
APP_ENV=testing     → GET|HEAD examples/forbidden ... routes.php:17   (1 route)
APP_ENV=production  → "no routes matching the given criteria"
```

## A3 · CI workflow

**File:** `.github/workflows/tests.yml` (new)

- PHP **8.3** (`composer.json` ka `^8.3` minimum), extensions: `mbstring, pdo_sqlite, sqlite3, gd, zip, intl`
- Node **22** + `npm ci` (lockfile maujood hai)
- `cp .env.example .env` → `composer install` → `key:generate` → `npm ci` → `npm run build` → `php artisan test`
- Composer cache (`hashFiles('composer.lock')`), concurrency group jo purani run cancel karti hai

## A4 · Test run

```
php artisan test
Tests:    109 passed (560 assertions)
Duration: 27.80s
```

Pehle 101 passed / 543 assertions tha → **+8 tests, +17 assertions**, zero regressions. `vendor/bin/pint --test` chaar changed PHP files par PASS.

## Changed files (scoped to this task)

```
 app/Core/Http/Controllers/AttachmentController.php | 48 ++++++++++++-----
 app/Modules/Examples/routes.php                    |  6 +++-
 config/attachments.php                             |  5 +++
 tests/Feature/AttachmentAccessTest.php             | 181 +++++++++++++++++++++ (new)
 .github/workflows/tests.yml                        |  58 ++++++++++++++++ (new)
 5 files changed, ~298 insertions(+), ~8 deletions(-)
```

> Note: repo mein pehle se bohat saara uncommitted kaam pada hai (email module, login3d, docs). `git diff --stat` woh sab bhi dikhata hai — upar sirf **is task ke** 5 paths hain.

---

# PART B — Extraction Plan (plan only)

## B1 · Target layout

```
khattakbits-core/                        (private repo, composer package)
├── composer.json
├── src/
│   ├── CoreServiceProvider.php
│   ├── Console/            CreateAdmin, PurgeBin, PruneEmails
│   ├── Exceptions/         DocumentException
│   ├── Http/
│   │   ├── Controllers/    Attachment, Auth, DocumentActivity, PasswordReset, Profile, Controller
│   │   └── Middleware/     EnsureUserActive, SecurityHeaders, HandleInertiaRequests
│   ├── Jobs/               SendEmailJob
│   ├── Models/             Attachment, AuditLog, DeletedDocument, DocumentView,
│   │                       EmailAccount, EmailLog, EmailTemplate, Setting, User
│   │   └── Concerns/       HasAttachments, IsDocument
│   └── Support/            Audit, Authority, DocumentColumns, DocumentRegistry,
│                           DocumentViews, EmailTemplates, MailHostGuard, Mailer,
│                           MenuBuilder, ModuleRegistry, Naming, PermissionCatalog,
│                           SettingsStore, TableQuery
├── config/                 attachments.php, documents.php, email.php, security.php
├── database/migrations/    (15 files, Step 0 ke baad)
├── routes/                 web.php, console.php
└── tests/                  (Testbench)
```

`khattakbits-ui/` baad mein: `resources/js/{layouts,ui,composables,login3d}` + `resources/css/app.css` ka base.

## B2 · `composer.json` (core)

```json
{
    "name": "khattakbits/core",
    "description": "KhattakBits shared kernel: audit, mailer, documents, RBAC, settings, modules",
    "type": "library",
    "license": "proprietary",
    "require": {
        "php": "^8.3",
        "illuminate/support": "^13.0",
        "illuminate/database": "^13.0",
        "illuminate/http": "^13.0",
        "illuminate/routing": "^13.0",
        "illuminate/console": "^13.0",
        "illuminate/view": "^13.0",
        "inertiajs/inertia-laravel": "^3.5",
        "spatie/laravel-permission": "^8.3"
    },
    "require-dev": {
        "orchestra/testbench": "^11.0",
        "phpunit/phpunit": "^12.5",
        "laravel/pint": "^1.27"
    },
    "autoload": { "psr-4": { "KhattakBits\\Core\\": "src/" } },
    "autoload-dev": { "psr-4": { "KhattakBits\\Core\\Tests\\": "tests/" } },
    "extra": {
        "laravel": { "providers": ["KhattakBits\\Core\\CoreServiceProvider"] }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

`orchestra/testbench ^11.0` Laravel 13 ke liye hai (v11.0.0 `laravel/framework ^13.0.0` accept karta hai — Packagist metadata, 12 Nov 2026). Exact patch install par pin karein.

## B3 · Move order (tests green after **every** step)

**Step 0 — cross-DB FK fix (BLOCKING, pehle).** `docs/platform/02-ARCHITECTURE-DECISIONS.md` §5.5: 5 migrations se `constrained('users')` hatana. Iske baghair tenancy boot hi nahi hoga aur baad mein yeh 5 published migrations ko touch karna MAJOR bump banega. **0.5d**

| Step | Kya move hota hai | Kyun is order par (green guarantee) | Effort |
|---|---|---|---|
| **1** | Package repo + `composer.json` + path repository host mein. Koi file move nahi. `KhattakBits\Core\` autoload active | Ab classes dono namespaces se load ho sakti hain; agla har step atomic move hai | 0.5d |
| **2** | `Support/Naming`, `Support/TableQuery`, `Support/DocumentColumns`, `Exceptions/DocumentException`, `Support/ModuleRegistry` | In paanchon ki koi app-class dependency nahi (sirf framework). Importers same commit mein update | 1d |
| **3** | **Mail + settings cluster (aik hi step):** `Models/Setting`, `Models/EmailAccount`, `Models/EmailLog`, `Models/EmailTemplate`, `Support/SettingsStore`, `Support/Mailer`, `Support/MailHostGuard`, `Support/EmailTemplates`, `Jobs/SendEmailJob`, `Console/PruneEmails`, `Models/User` + 4 migrations | Yeh cluster **mutually recursive** hai (`SettingsStore` ↔ `Mailer`, `User` → `EmailTemplates`). Akele move karne par circular break ho kar suite red ho jata hai. `User` bhi yahin — host `App\Models\User extends KhattakBits\Core\Models\User` (thin) | 2d |
| **4** | `Models/AuditLog`, `Support/Audit` + 3 migrations (`audit_logs` + 2 alters) | `IsDocument` (Step 5) `Audit` par depend karta hai, is liye pehle | 0.5d |
| **5** | `Models/Attachment`, `DeletedDocument`, `DocumentView`, `Concerns/HasAttachments`, `Concerns/IsDocument`, `Support/DocumentRegistry`, `Support/DocumentViews`, `Http/Controllers/AttachmentController`, `DocumentActivityController`, `Console/PurgeBin` + 3 migrations | Documents ka poora graph ab Step 2+4 par khara hai. `DocumentRegistry` ka `App\Models\User` hint `Authenticatable` par switch | 1.5d |
| **6** | `Support/Authority`, `Support/PermissionCatalog`, `Support/MenuBuilder` | RBAC + menu; `config('khattakbits.user_model')` resolver yahin add hota hai | 1d |
| **7** | `Http/Controllers/{Controller,AuthController,PasswordResetController,ProfileController}`, `Http/Middleware/{EnsureUserActive,SecurityHeaders,HandleInertiaRequests}`, `CoreServiceProvider`, `config/{attachments,documents,email,security}.php`, `routes/web.php` + `console.php` ka schedule | Provider last: jab tak sab classes package mein nahi, provider host mein rehta hai. Routes/config/provider aik hi commit mein — warna route:list diff dikhega | 1.5d |
| **8** | `resources/js/core/**` (51 files) → `khattakbits/ui` + Vite alias + Tailwind `@source` + Inertia `resolve` | PHP se independent; `npm run build` har step par check | 3d |
| **9** | Skeleton cleanup: `app/Core` delete, `bootstrap/providers.php` se `CoreServiceProvider` hatao (auto-discover), `composer.json` se `laravel/laravel` → `khattakbits/product-skeleton`, README | Final; ab host mein core ka koi file nahi | 1d |

**Total ≈ 12.5 days** (core 9.5d + ui 3d). Step 0 alag 0.5d.

**Har step ka invariant:** aik class ka purana naam (`App\Core\X`) aur naya naam (`KhattakBits\Core\X`) **same waqt** maujood nahi hote. Isliye "dono jagah" wala confusion nahi aata, aur `class_alias` shim ki zaroorat nahi.

## B4 · Namespace rename strategy

`App\Core` → `KhattakBits\Core`. String `App\Core` unambiguous hai (koi `App\CoreX` class nahi), is liye plain replace safe hai — **magar** blind `sed -i` se pehle dry-run aur backup lazmi.

**Script:** `tools/rename-namespace.php` (host repo mein, ek dafa chalta hai)

```php
<?php
// usage: php tools/rename-namespace.php "App\\Core" "KhattakBits\\Core" [--write]
[$_, $from, $to] = $argv;
$write = in_array('--write', $argv, true);
$dirs = ['app', 'routes', 'config', 'database', 'tests', 'bootstrap'];
$hits = 0;

foreach ($dirs as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $code = file_get_contents($path);
        $new = str_replace($from, $to, $code, $count);
        if ($count === 0) {
            continue;
        }
        $hits += $count;
        printf("%-70s %d\n", $path, $count);
        if ($write) {
            copy($path, $path.'.bak');
            file_put_contents($path, $new);
        }
    }
}
printf("\n%s %d replacements in %s\n", $write ? 'WROTE' : 'DRY-RUN:', $hits, $write ? 'files' : 'files (no change)');
```

**Sequence (per step, na ke poori repo par aik dafa):**

```bash
git switch -c extract/step-2
php tools/rename-namespace.php 'App\Core' 'KhattakBits\Core'          # dry-run, count dekho
php tools/rename-namespace.php 'App\Core' 'KhattakBits\Core' --write
git mv app/Core/Support/Naming.php ../khattakbits-core/src/Support/Naming.php   # baaki files
composer dump-autoload -o
php artisan test
find app routes config database tests bootstrap -name '*.bak' -delete
```

**Verification (rename ke baad, har step):**

```bash
grep -rn 'App\\Core' app routes config database tests bootstrap   # zero hona chahiye (us step ke scope mein)
composer dump-autoload -o && php artisan about | head -5
```

**Kya rename nahi hota:** `config('attachments.*')`, `config('documents.*')`, table names, route names, permission strings (`examples.entries.view`), Inertia page names. Sirf PHP namespace badalta hai — is liye behaviour change zero.

## B5 · Host app package kaise consume karega

**Dev (path repository, symlink):**

```json
"repositories": [
  { "type": "path", "url": "../khattakbits-core", "options": { "symlink": true } }
],
"require": { "khattakbits/core": "*@dev" }
```

Vite symlink ke saath: `server.fs.allow: ['..', '../khattakbits-core', '../khattakbits-ui']`.

**Prod (private VCS):**

```bash
composer config repositories.khattakbits-core vcs git@github.com:khattakbits/core.git
composer require khattakbits/core:^1.4
```

**Config publish:** package `mergeConfigFrom()` karta hai (same keys: `attachments`, `documents`, `email`, `security`) — is liye `config('attachments.types')` calls **nahi** badalti.

```bash
php artisan vendor:publish --tag=khattakbits-config    # host override ke liye copy
```

**Migrations:** provider `loadMigrationsFrom(__DIR__.'/../database/migrations')`. Multi-DB mein dono chalengi:

```bash
php artisan migrate                 # central
php artisan tenants:migrate         # har tenant
```

**Routes:** package `routes/web.php` `loadRoutesFrom()` se load hoti hai; andar `Route::middleware(config('khattakbits.middleware', ['web']))` — host apni middleware list de sakta hai. Host `routes/web.php` sirf modules ka loop rakhta hai.

**Inertia pages:** `resources/js/app.js` mein `pages` shorthand **kaam nahi karega** (woh aik hi glob banata hai). Explicit `resolve`:

```js
createInertiaApp({
  resolve: async (name) => {
    const local = import.meta.glob('./Pages/**/*.vue')
    const vendor = import.meta.glob('../../vendor/khattakbits/ui/resources/js/Pages/**/*.vue')
    const key = (m) => Object.keys(m).find((k) => k.endsWith(`/Pages/${name}.vue`))
    const hit = key(local) ?? key(vendor)
    if (!hit) throw new Error(`Page not found: ${name}`)
    return (await (local[hit] ?? vendor[hit])()).default
  },
})
```

## B6 · Per-step checklist

```bash
# 1. route baseline (step 1 se pehle ek dafa)
php artisan route:list --except-vendor --json > /tmp/routes-base.json

# 2. har step ke baad
composer dump-autoload -o
php artisan test                                   # 109 passed se neeche na jaye
php artisan route:list --except-vendor --json > /tmp/routes-step.json
diff <(jq -S 'map(.method,.uri,.name)' /tmp/routes-base.json) \
     <(jq -S 'map(.method,.uri,.name)' /tmp/routes-step.json)   # sirf expected diff
npm run build                                       # "Unable to locate file in Vite manifest" na aaye
vendor/bin/pint --test app tests config
```

**Definition of done (har step):** tests green · route diff sirf expected · build pass · pint pass · `grep App\\Core` us scope mein zero · commit message `extract(step-N): ...`.

## B7 · Risks

| Risk | Mitigation |
|---|---|
| Circular deps (mail ↔ settings ↔ user) | Step 3 mein aik saath move; alag karne ki koshish na karein |
| Route names duplicate (package + host dono register) | Step 7 mein package routes add karte waqt host se same routes **usi commit** mein hatao |
| Migration timestamp collision | Package migrations ke naam `0001_..`–`2026_..` wahi rakhein; `loadMigrationsFrom` order se chalti hain |
| Vite vendor glob build par fail | `server.fs.allow` + `resolve.alias` dono, aur `npm run build` (dev server par bharosa na karein) |
| Rollback | Har step alag branch/PR; `git revert` se step isolate ho jata hai (is liye aik step = aik commit) |

---

*Part A implemented + verified. Part B plan hai — `IMPLEMENT` ke baghair koi package nahi banaya gaya.*
