# 03 — Extraction Plan (Part A + B executed · record in Part C)

**Date:** 12 Nov 2026 (Part C added after execution) · **Revised:** 13 Nov 2026 (**Part D** — `07` ke mutabiq path-based follow-up: migration split, `core` v2.0.0, `UPGRADE.md`; changelog neeche)
**Repo:** `E:/LaraProjects/saas-starter` (branch `extract/package-skeleton`, off `feat/cross-db-fk`)
**Depends on:** `docs/platform/02-ARCHITECTURE-DECISIONS.md` (§0 D1–D5, §5.5 Step 0), `docs/platform/07-MULTI-TENANCY-DESIGN.md` (§2 table split, §7 module migrations, §11.5)
**Rule:** Part A code hai (done). Part B plan tha — ab **execute ho chuka hai**, aur jo actually hua wo Part C mein hai (deviations ke saath).

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

# PART C — Executed (record)

Part B ke steps **2 se 9 tak implement ho gaye**. Step 0 pehle hi ho chuka tha. Har step ke baad `php artisan test` chalaya gaya — **117 passed (588 assertions)** har dafa, aur `npm run build` bhi (734 modules).

## Branches aur repos

| Kahan | Kya |
|---|---|
| `saas-starter` branch `extract/package-skeleton` | `feat/cross-db-fk` (`6cdbf61`) se off; 8 commits |
| `E:/LaraProjects/khattakbits-core` (git `main`) | 6 commits, 40 PHP files, 12 migrations |
| `E:/LaraProjects/khattakbits-ui` (git `main`) | 1 commit, 51 files |

Dono package directories **apne git repos** hain. Yeh plan mein likha nahi tha magar zaroori tha: files host repo se `mv` hoti hain, aur agar package tracked na ho to code kahin bhi version-controlled nahi rehta — `git status` host mein sirf deletions dikhata hai.

## Commits

```
e825813 refactor(core): extract Support/Exceptions primitives
ce6ddac refactor(core): extract mail/settings cluster and User model
d86800f refactor(core): extract audit cluster
470de99 refactor(core): extract documents cluster
fbaf53f refactor(core): extract RBAC and menu cluster
c8360a6 refactor(core): extract HTTP surface, routes, config and provider
18a32d3 refactor(ui): extract resources/js/core to @khattakbits/ui
00d5b11 chore: skeleton identity and README
```

## Kya kahan gaya

| Package | Contents |
|---|---|
| `khattakbits/core` | `Support/` (Naming, TableQuery, DocumentColumns, ModuleRegistry, SettingsStore, Mailer, MailHostGuard, EmailTemplates, DocumentRegistry, DocumentViews, Audit, Authority, PermissionCatalog, MenuBuilder, Users) · `Models/` (User, Setting, EmailAccount, EmailLog, EmailTemplate, AuditLog, Attachment, DeletedDocument, DocumentView, Concerns/{HasAttachments,IsDocument}) · `Http/` (5 controllers, 2 middleware, base `Controller`) · `Jobs/SendEmailJob` · `Console/` (CreateAdmin, PurgeBin, PruneEmails) · `config/` (khattakbits, attachments, documents, email, security) · `routes/web.php` · 12 migrations |
| `@khattakbits/ui` | 51 files: `layouts/`, `ui/`, `composables/`, `login3d/` |
| host mein bacha | `app/Models/User.php` (thin), `app/Http/Controllers/Controller.php`, `HandleInertiaRequests`, `app/Modules/**`, `routes/web.php` (sirf module loop), `config/*.php` (published copies), `resources/js/Pages/**` |

`app/Core` **delete ho gaya** — grep se confirm: `App\Core` ka zero reference (sirf historical docs mein).

## Plan se deviations (aur kyun)

1. **Migrations step 3 mein gayi, step 7 mein nahi.** Plan ne unhein provider ke saath rakha tha, magar models aur unki tables aik hi unit hain — models package mein aur tables host mein rakhna adhoora hai. Is liye `loadMigrationsFrom()` step 3 mein hi provider mein add kiya. **6 migrations** (4 nahi): users, admin columns, settings, email_accounts, email_logs, email_templates. Laravel merged paths ko filename se sort karta hai, is liye `0001_..` ordering barqarar rahi.

2. **`App\Models\User` poora model ban kar gaya**, thin subclass host mein. `Database\Factories\UserFactory` ka import aur `@use HasFactory<UserFactory>` generic hata diya (wo class host ki hai) — `HasFactory` runtime class name se factory resolve karta hai, is liye `User::factory()` bilkul waisa hi chalta hai. `#[Fillable]` / `#[Hidden]` attributes subclass par bhi lagte hain kyunke Laravel 13 ka `resolveClassAttribute()` parent chain walk karta hai.

3. **Config files duplicate hain — jaan boojh kar.** `mergeConfigFrom()` config cache hone par **no-op** hai. Sirf package copy rakhne ka matlab hota ke `config:cache` ke baad limits gayab. Is liye: package defaults + host published copies, aur provider `vendor:publish --tag=khattakbits-config` deta hai.

4. **`Support\Users` resolver add kiya.** Plan ne step 6 mein `config('khattakbits.user_model')` ka zikr kiya tha; usse step 7 mein implement kiya — `khattakbits.user_model` → `auth.providers.users.model` → core `User`. `PasswordResetController` aur `CreateAdmin` isse chalte hain, is liye host ka thin `User` hi instantiate hota hai (verify: `Users::model()` → `App\Models\User`).

5. **`KhattakBits\Core\Http\Controllers\Controller` naya class.** Plan ne miss kiya tha ke `AttachmentController` aur `DocumentActivityController` host ke base controller par depend karte hain. Package ka apna base bana kar dono repoint kiye.

6. **Vite `resolve.dedupe` lazmi nikla.** Sirf `server.fs.allow` + alias kaafi nahi tha — build `@inertiajs/vue3` resolve karne par fail hota tha, kyunke project root se bahar wali files apne directory chain mein `node_modules` dhoondti hain. `dedupe: ['vue', '@inertiajs/vue3', '@tanstack/vue-query', '@tanstack/vue-virtual', 'three']` se hal hua.

7. **Alias ka naam `@core` hi rakha**, `@khattakbits/ui` nahi. 40 host files ke imports badalne ka koi faida nahi tha; sirf alias ka target badla. Package ke **andar** ke 18 imports relative kar diye (`tools/relativize-core-imports.mjs`), taake package kisi bhi host alias par depend na kare.

8. **`composer.json` constraint `dev-main`**, `*@dev` nahi — `composer validate` unbound constraint par warn karta hai.

## Do cheezein jo plan mein thi magar nahi ki gayin

- **Inertia `resolve` merge nahi chahiye tha.** Pages host mein hi hain (`resources/js/Pages`), sirf components move hue — is liye `createInertiaApp` ka page resolution bilkul waisa hi hai.
- **`resources/js/app.js` chhua nahi gaya.** `@inertiajs/vite` ka `<pages>` shorthand pehle se hi single glob use karta hai.

## Rollback

Har step aik alag commit hai, aur package commits alag repos mein. Rollback ke do raaste:

- Host: `git revert <sha>` us step ke liye (step 3+ ke liye package repo mein bhi revert karna hoga, warna class missing hogi).
- Poora extraction: host branch `feat/cross-db-fk` par wapas, aur dono package directories chhod dein — kuch bhi shared nahi tha.

## Aakhri verification

```
php artisan test     → 117 passed (588 assertions), ~26s
npm run build        → 734 modules transformed, built in ~3.5s
composer validate    → ./composer.json is valid
grep -r "App\\Core"  → zero (app, routes, config, database, tests, bootstrap)
php artisan route:list → login, logout, profile, attachments, forgot-password sab maujood
```

---

# PART D — `07` ke mutabiq follow-up (path-based tenancy)

Part A/B/C ne extraction **complete** kar diya (`khattakbits/core` v1.x, `@khattakbits/ui` v1.x). Phir
`07-MULTI-TENANCY-DESIGN.md` ne tenancy ko **path-based** karar diya, aur uska `§11.5` teen cheezein maangta hai
jo extraction ke **baad** ka kaam hain — magar **package release se pehle**, kyunke woh package ka public
contract tor rahe hain.

| # | Kaam | Status | Kyun ab |
|---|---|---|---|
| **D1** | **Step 0 — cross-DB FK fix** (5 migrations) | ✅ **Done** — branch `feat/cross-db-fk`, extraction se pehle | `02` §5.5. Path-based tenancy se iska koi taalluq nahi, magar yeh **pehle** hona chahiye tha taake tenant DB par cross-DB FK na rahe. Verify: `grep -rn "constrained('users')\|references('users')" database/migrations vendor/khattakbits/core/database/migrations` — tenant tables mein zero hit |
| **D2** | **Migration folder split** — `{central,tenant}` | ⬜ **Pending** — next MAJOR se pehle | Aaj `loadMigrationsFrom()` **saari** migrations dono jagah chalata hai: `php artisan migrate` central par, `php artisan tenants:migrate` har tenant par. `users` central table hai magar har tenant DB mein bhi ban jati hai; `attachments` tenant table hai magar central mein bhi. `07` §7.1 ka split isay theek karta hai |
| **D3** | **`khattakbits/core` v2.0.0 (MAJOR)** | ⬜ **Pending** | Do breaking changes: (a) core routes ka split (`07` §3.4 — `attachments` + `document-activity` tenant ke andar `{tenant}` ke saath), (b) `menu.php` contract mein `path` key (`07` §5.3) |
| **D4** | **`UPGRADE.md`** | ⬜ **Pending** | D3 ke dono breaking changes ke liye codemod instructions. Bina iske har product ka upgrade manual hai |

## D2 · Migration split — exact inventory

`07` §7.1 ka split **`§2.3` ke per-table faisle** se derive hota hai, guess se nahi. Aaj ki asli ginti:

**Core package — `vendor/khattakbits/core/database/migrations/` (12 migrations → 2 central, 10 tenant):**

| Migration | Tables | Kahan |
|---|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | **central** |
| `2026_10_27_070652_add_admin_columns_to_users_table.php` | `users` (alter) | **central** |
| `2026_10_26_113043_create_attachments_table.php` | `attachments` | tenant |
| `2026_10_27_070653_create_settings_table.php` | `settings` | tenant |
| `2026_10_27_070654_create_audit_logs_table.php` | `audit_logs` | tenant |
| `2026_10_28_061446_add_user_agent_to_audit_logs_table.php` | `audit_logs` (alter) | tenant |
| `2026_10_28_102652_create_document_tables.php` | `deleted_documents`, `document_sequences` | tenant |
| `2026_10_29_110000_create_document_views_table.php` | `document_views` | tenant |
| `2026_10_29_110100_add_subject_index_to_audit_logs_table.php` | `audit_logs` (alter) | tenant |
| `2026_10_29_120000_create_email_accounts_table.php` | `email_accounts` | tenant |
| `2026_10_29_120100_create_email_logs_table.php` | `email_logs` | tenant |
| `2026_10_29_130000_create_email_templates_table.php` | `email_templates` | tenant |

**Host — `database/migrations/` (3 migrations → 2 central, 1 tenant):**

| Migration | Tables | Kahan |
|---|---|---|
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | **central** |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | **central** |
| `2026_10_26_101855_create_permission_tables.php` | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` | **tenant** |

**Total: 4 central, 11 tenant.**

> **Yeh ginti zaroori kyun hai:** `07` §7.1 ki pehli shakal kehti thi "aaj ki 12 migrations `central/` mein shift" aur "`tenant/` khaali shuru" — jo `§2.2`/`§2.3` se **seedha contradict** karta tha. Us split par chale to `attachments`, `audit_logs`, `settings`, `email_*` sab **central** mein chali jatin: yani har tenant ka data ek hi table mein, aur `§8` ka "per-tenant everything" jhoota. `07` v3 mein correct ho gaya — lekin **yeh ginti implementation se pehle verify karein**, kyunke naya module add hone par badlegi.

**Permission tables ka move khaas hai.** Woh aaj **host** ki `database/migrations/` mein hain, package mein nahi — extraction ke waqt woh yahin chhod di gayin (Part C, "Kya kahan gaya"). `§2.3` kehta hai roles **per-tenant** hain, is liye woh `database/migrations/tenant/` mein jayengi. Agar woh central mein rah jayein to **saare tenants ek doosre ke roles dekh lete hain** — aur spatie ka cache key issue (`§2.3.1a`) us ke upar privilege escalation bana deta hai.

**`sessions` ka ulat-pher:** woh `create_users_table.php` ke **andar** banti hai, yani `central/` mein jayegi. Yeh sahi hai (`§2.1`: ek origin, ek session) — magar iska matlab yeh hai ke **tenant DB mein `sessions` table hogi hi nahi**, kyunke `tenants:migrate` us migration ko nahi chalayega. Is liye `session.connection = 'central'` pin karna (`§2.4`) optional nahi, **lazmi** hai.

### D2.1 Provider change (`07` §7.3 ka exact diff)

```php
// Aaj — CoreServiceProvider.php:53-61
$this->loadMigrationsFrom($this->packagePath('database/migrations'));

foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
    $migrations = $module['path'].'/Database/Migrations';
    if (is_dir($migrations)) {
        $this->loadMigrationsFrom($migrations);
    }
}
```

```php
// Naya — flag ke hisaab se do mode (D2.3)
$registry = $this->app->make(ModuleRegistry::class);
$tenancy = (bool) config('khattakbits.tenancy.enabled', false);

$folders = MigrationPaths::folders($this->packagePath('database/migrations'), $registry);

// 1) `php artisan migrate` — tenancy off par DONO folders (CP/ek DB), on par sirf central
foreach (MigrationPaths::forMigrate($folders, $tenancy) as $path) {
    $this->loadMigrationsFrom($path);
}

// 2) TENANT — `loadMigrationsFrom` se register NAHI karenge, warna
//    `php artisan migrate` unhein bhi central DB par chala dega.
if ($tenancy) {
    $params = config('tenancy.migration_parameters', []);

    $params['--path'] = array_values(array_unique(array_merge(
        $folders['tenant'],
        $params['--path'] ?? [],
    )));

    config(['tenancy.migration_parameters' => $params]);
}
```

**Chaar traps jo Part B ke risks mein nahi thay:**

1. **`config(['tenancy.migration_parameters.--path' => ...])` kaam nahi karta.** `Arr::set()` dot ko nesting samajhta hai — `'--path'` do level ban jata hai. **Poora array** replace karna hoga, jaisa upar hai.
2. **`class_exists(\Stancl\Tenancy\Tenancy::class)` guard galat tha — config flag use karein.** Guard product ke liye theek lagta hai, magar **CP** mein tenancy install hi nahi, is liye guard false hota hai aur tenant migrations **kabhi** register nahi hotin — CP ka schema adhoora reh jata hai (tafseel **D2.3**).
3. **`--path` array hai, string nahi.** `MigrateCommand` `--path` milne par **sirf** wohi paths use karta hai (`BaseCommand::getMigrationPaths()`), `migrator->paths()` ignore karta hai. Is liye core + saare modules ki tenant migrations **usi ek array** mein aani chahiye.
4. **Laravel subfolders khud nahi uthata.** `database/migrations/*.php` glob hota hai, `database/migrations/central/*.php` **nahi**. Move ke baad host ki dono folders explicitly register karni hongi (D2.3) — warna `php artisan migrate` ko kuch bhi nahi milta aur app bina `cache`/`jobs`/`permissions` tables ke chalti hai.

### D2.2 Filename immutability — sab se aasan galti

Split ke baad `migrations` table mein purane entries `central/` ke naam se hain, naye `tenant/` se. Laravel
migration ko **filename** se match karta hai, path se nahi. Is liye:

> **Folder badle, filename bilkul na badle.**

Agar `2026_10_26_113043_create_attachments_table.php` ko `tenant/` mein move karte waqt rename kar diya, to
`tenants:migrate` usay **pending** samajh kar dobara chalayega aur `table already exists` par fail hoga — har
tenant par, provisioning ke beech mein.

**Migration ke liye ek test:** `tests/Feature/Tenancy/MigrationSplitTest.php` — do tenants provision karein,
phir `SELECT table_name FROM information_schema.tables WHERE table_schema = ...` se verify karein ke tenant DB
mein **`users` nahi hai** aur `attachments` **hai**. Yeh test D2 ke baad permanent rehna chahiye, kyunke split
ki galti sirf runtime par dikhti hai.

> **P0 mein yeh test nahi likha ja sakta** — us waqt tenancy install hi nahi hoti, is liye `tenants:migrate`
> maujood nahi. P0 ka test **file-level** hota hai (folder counts + FK scan); do-tenant wala test P5 mein aata
> hai. Dono ka rishta D2.3 mein hai.

### D2.3 CP / single-tenant mode — `khattakbits.tenancy.enabled`

**Masla jo D2/D2.1 mein chhoot gaya tha (RK21).** Host app **khud control plane hai** aur uske paas tenancy
**nahi** (`04` §7 scope note: "`stancl/tenancy` **is CP mein nahi** — CP single DB hai"). D2.1 ka purana
`class_exists(\Stancl\Tenancy\Tenancy::class)` guard CP mein **false** hai — yani tenant migrations **kabhi**
register nahi hotin, `php artisan migrate` sirf `central/` chalata hai, aur CP ke paas `audit_logs`, `settings`,
`email_accounts`, `email_logs`, `email_templates`, `attachments`, `deleted_documents`, `document_views`,
`document_sequences`, `permissions`/`roles` — **kuch nahi** hota. Pehla page render `Table 'audit_logs' doesn't
exist` par 500 deta hai.

`07` §7.3 ka note 2 ("tenancy na ho to starter kit single-tenant chalta rehta hai") is maamle mein **ghalat**
hai: single-tenant app ko un tables ki zaroorat **hoti hai** — woh "ek tenant" hai, "zero tables" nahi.

**Fix — package ki maujoodgi ke bajaye explicit config:**

| Key | Env | Default | Matlab |
|---|---|---|---|
| `khattakbits.tenancy.enabled` | `KB_TENANCY_ENABLED` | `false` | `false` = **ek DB** (CP, starter kit, tests); `true` = **multi-DB product** |

| `enabled` | `php artisan migrate` | `tenants:migrate` |
|---|---|---|
| `false` (default) | central **+** tenant dono → poora schema ek DB mein, **23 tables** | command maujood nahi (tenancy install nahi) |
| `true` | sirf central → **8 tables** | tenant folders `--path` se → **14 tenant + 1 module** |

**Kahan register hota hai — do jagah, dono lazmi:**

| Kaun | Kya register karta hai | Kahan |
|---|---|---|
| **Package** | core `central/` + `tenant/` + module paths (`ModuleRegistry::migrationPaths()` dono context ke liye) | `CoreServiceProvider::boot()` |
| **Host** | apni `database/migrations/{central,tenant}` | `AppServiceProvider::boot()` |

**Trap #4 tafseel se:** Laravel ka default `migrate` `database_path('migrations')` ke **sirf top-level
`*.php`** files uthata hai — `central/` aur `tenant/` subfolders ko **nahi**. Yani move ke baad host ki 3
migrations (cache, jobs, permissions) **khamoshi se gayab** ho jati hain: `cache`/`jobs` ke baghair queue aur
session tootte hain, `permissions` ke baghair poora RBAC. Is liye `loadMigrationsFrom()` host provider mein bhi
lazmi hai, sirf package provider kaafi nahi.

**Corrected done-when** — `11` §4.1 ka `migrate --pretend | grep -c "create table"` **do waja se galat tha**:
woh statements ginta hai (migration files nahi), aur CP par `4` hona **bug** hai, acceptance nahi.

```bash
# 1. Files — folder ke hisaab se (P0 ka test isi ko lock karta hai)
ls vendor/khattakbits/core/database/migrations/central/*.php | wc -l   # → 2
ls vendor/khattakbits/core/database/migrations/tenant/*.php  | wc -l   # → 10
ls database/migrations/central/*.php | wc -l                           # → 2
ls database/migrations/tenant/*.php  | wc -l                           # → 1
ls app/Modules/*/Database/Migrations/tenant/*.php | wc -l              # → 1 (demo_entries)

# 2. Tables — asli schema, temporary SQLite par (pretend nahi)
DB_CONNECTION=sqlite DB_DATABASE=/tmp/kb-schema.sqlite php artisan migrate:fresh --force
# → 23 tables: 8 central + 14 tenant + 1 module
```

**Tests jo P0 mein likhe jayenge:**

| Test | Kya prove karta hai | Kab |
|---|---|---|
| `MigrationSplitTest` | Folder counts exactly (2/10 core, 2/1 host, 0/1 module), filenames unchanged, tenant folders mein `users` ka koi FK nahi | P0 |
| `CentralSchemaParityTest` | `KB_TENANCY_ENABLED=false` par `migrate:fresh` → saari **23** tables maujood (D1 guard ke saath) | P0 |
| `MigrationPathsTest` | Path-resolution dono flag values par sahi folders deta hai | P0 |
| `MigrationSplitTest` (tenant version) | Do tenants provision, tenant DB mein `users` nahi / `attachments` hai | **P5** |

**Effort ka asar:** yeh flag `07` §12.1 #2 ke 2.5d mein **shamil nahi** tha (wahan sirf split + Step 0 hai) —
`11` §4.1 mein `3b` row ke saath **TBD** likha hai. Asli kaam: ek config key, `MigrationPaths` helper, host
provider ki 6 lines, aur 3 tests.

## D3 · `khattakbits/core` v2.0.0 — do breaking changes

### D3.1 Core routes ka split (`07` §3.4)

Aaj `core/routes/web.php` mein `attachments` aur `document-activity` **central** hain. Path-based mein woh
**tenant-scoped** hain:

```php
// v1.x — central
Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);

// v2.0 — tenant ke andar, {tenant} PEHLA param
Route::get('/{tenant}/attachments/{attachment}', [AttachmentController::class, 'show']);
```

**`{tenant}` pehla parameter hona lazmi hai.** `InitializeTenancyByPath` `parameterNames()[0]` padhta hai
(`07` §1.2). `/{tenant}/attachments/{attachment}` theek hai; `/attachments/{tenant}/{attachment}` **nahi** —
woh chupchaap non-tenant route ban jayega aur `AttachmentController` central DB par attachment dhoondega.

**Route names na badlein.** `attachments.show` hi rahe — sirf URI badalta hai. Warna har host ke `route()`
calls toot jayenge aur codemod ka scope barh jayega.

### D3.2 `menu.php` contract mein `path` key (`07` §5.3)

Yeh subtle hai aur ise miss karna **production bug** hai. Aaj `AccountingDemo/routes.php:8` menu ka `href`
**route URI** ke tor par use karta hai:

```php
// v1.x — href hi route URI hai
Route::get($item['href'], ...);
```

Agar hum `href` mein slug daal dein (`/acme/accounting/invoices`), to **route registration** mein slug bake ho
jayega — yani `acme` ke liye register hui route, `beta` ke liye nahi. Tenant A ki routes banti hain, tenant B
ki 404. Is liye do alag keys:

```php
// v2.0
[
    'key'  => 'accounting.invoices',
    'path' => '/accounting/invoices',              // NAYA — route URI, slug-free, static
    'href' => tenantUrl('/accounting/invoices'),   // runtime, slug ke saath
]
```

`path` **static** hai (route registration ke liye), `href` **runtime** hai (`tenantUrl()` se, `07` §5.2).
Host ka `routes.php` `path` par register karega aur route ko `{tenant}` group ke andar rakhega.

**Yeh v2.0 (MAJOR) kyun hai:** har product ka `menu.php` **aur** har product ka `routes.php` touch hota hai.
MINOR mein nahi ho sakta — aur agar galti se MINOR mein chala diya, to purane products ka `routes.php` `href`
par register karta rahega aur **`path` key ko ignore karega**, yani menu dikhega magar route nahi milega.

## D4 · `UPGRADE.md` — kya likhna hai

Package repo mein `UPGRADE.md` (v1 → v2), teen sections:

1. **Route split** — agar host ne core routes `route()` se generate kiye hain to `tenant_url()` par switch karein; agar `href` hardcode hai to `path` + `tenantUrl()` pattern par jayein. `route('attachments.show', ...)` ab `{tenant}` param maangega — `07` §1.3 ka `URL::defaults` sirf tab kaam karta hai jab request mein tenancy initialized ho.
2. **`menu.php` `path` key** — har menu item mein `path` add karein (slug-free route URI). **Codemod:** `href` se leading `/{slug}` strip kar ke `path` mein daal dein, aur `href` ko `tenantUrl(path)` se replace karein. `07` §5.4 ka CI gate (C1–C7) is codemod ka automated version hai — `UPGRADE.md` usi ko link kare.
3. **Migrations** — `{central,tenant}` split ke baad `php artisan migrate` **aur** `php artisan tenants:migrate` dono chalane hain; filename na badalne ki wajah (D2.2) aur "table already exists" ka ilaaj.

**`UPGRADE.md` ka ek chautha section jo `07` mein nahi tha — lekin zaroori hai:** **rollback**. Agar product v2.0
par upgrade kar ke `path` key bhool jaye, to usay v1 par wapas jane ka rasta chahiye. Likhein: v2 routes `{tenant}`
ke baghair bhi **register** nahi hoti (middleware fail karti hai), is liye rollback = package pin `^1.4` + `menu.php`
revert + `composer update`. Yeh 10 minute ka kaam hona chahiye, nahi to koi upgrade karega hi nahi.

---

## Changelog

| v | Date | Kya |
|---|---|---|
| **v1** | 12 Nov 2026 | Part A (attachment IDOR fix, forbidden demo route, CI, test run) + Part B (target layout, move order, namespace strategy, host consumption, checklist, risks) |
| **v2** | 12 Nov 2026 | **Part C** — jo actually hua: steps 2–9 executed, 117 tests / 588 assertions, 8 deviations (migrations step 3 mein, `User` poora model, config duplicate, `Support\Users`, package base controller, Vite `dedupe`, `@core` alias, `dev-main` constraint), 2 cheezein jo nahi ki gayin, rollback, aakhri verification |
| **v3** | 13 Nov 2026 | **Part D** — `07` §11.5 ke teen follow-up items: D1 Step 0 (done, verify command), **D2 migration split** (exact inventory: core 2 central/10 tenant, host 2 central/1 tenant = **4/11**; provider diff; 3 traps; filename immutability; split test), **D3 `core` v2.0.0** (route split with `{tenant}` first param, `menu.php` `path` key + why it is MAJOR), **D4 `UPGRADE.md`** (4 sections incl. rollback) |

**Part D ka asar:** extraction **complete** hai — Part D usay **multi-tenant** banata hai, aur woh package ke
public contract ko tor raha hai (routes + menu). Is liye yeh **v2.0.0 (MAJOR)** hai, aur isay `07` §12 ke effort
(items 2, 3, 5, 7 — kul ≈ 6.5 din) mein gina gaya hai, `03` ke apne effort mein nahi — warna woh kaam do
jagah count hoga.

---

*Yeh document record hai — Part A/B/C executed, Part D planned. Part D ke teen items **pending** hain aur unka
effort `07` §12 mein hai, is doc mein nahi.*
