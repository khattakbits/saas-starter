# Starter Kit Audit — 27 Oct 2026

**Scope:** `app/` (Core + Modules), `routes/`, `resources/js/` (Vue 3 + Inertia), `config/`, `database/`, build tooling.  
**Method:** read every core file, then **reproduced** each finding against the running application (artisan tinker, HTTP kernel dispatch, and throwaway feature tests on the sqlite test DB). Nothing below is speculative — the evidence column says how it was proven.

**Verdict:** the architecture is genuinely good — module registry, permission catalog, whitelisted sort maps, cursor pagination. The problems are concentrated in three places: the exception handler (which is currently broken for *every* error), the authorization boundary on attachments and roles, and the total absence of mobile/keyboard polish.

---

## Scorecard

| Area              | Grade  | One-line reason                                            |
| ----------------- | ------ | ---------------------------------------------------------- |
| Architecture      | **A−** | Clean module system, no god classes, no raw SQL anywhere   |
| Backend security  | **D**  | Two privilege-escalation paths + attachment IDOR           |
| Error handling    | **F**  | Every 403/404/validation error returns a 500               |
| Data integrity    | **C**  | Silent mass-assignment drops, no transactions              |
| UI craft          | **B**  | Consistent, thoughtful components; excellent empty states  |
| UI responsiveness | **F**  | Zero responsive breakpoints in the entire codebase         |
| Accessibility     | **C+** | Good ARIA on tables/inputs, but buttons have no focus ring |
| Testing / CI      | **F**  | 2 example tests, 1 failing, no CI, Pint fails              |

---

## P0 — Fix before you deploy anything

### EX-1 · Every error response is a 500 · `bootstrap/app.php:23`

The exception callback type-hints `Response`, but `bootstrap/app.php` never imports it. In the global namespace that resolves to a class that does not exist, so PHP throws a `TypeError` **before any of your logic runs** — for every single exception.

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->respond(function (Response $response, ...) { // ← undefined class
```

**Proven:** `GET /examples/forbidden` and `GET /nope` both throw  
`TypeError: Argument #1 ($response) must be of type Response, Illuminate\Http\Response given`.  
`php artisan test` fails on exactly this line. A wrong password (ValidationException) hits it too.

**Impact:** wrong password → 500 instead of "credentials do not match". Any validation error → 500 instead of field errors. Every 403/404 → 500. `Pages/Errors/Error.vue` is dead code — it can never render.

**Fix:**

```php
use Symfony\Component\HttpFoundation\Response;   // add to imports
```

Then re-run `php artisan test` — the suite goes green.

---

### AT-1 · Attachment IDOR: any logged-in user can read and delete anyone's files · `AttachmentController.php:26-80`

`authorizeType()` only checks the *type* permission (`config('attachments.types.$type.can')`). For `demo` that is `null`, i.e. every authenticated user. There is no check that the caller owns the record, or that the record exists at all.

**Proven** — as a user with **0 permissions**:

```
acting as: khan@gmail.com perms=0
target attachment id=2 owner=1
DOWNLOAD: 200 | attachment; filename=admin-secret.txt
DELETE:   204 | row_gone=true
```

`index()` is worse: `?type=demo&id=1..N` enumerates every attachment in the system.

**Fix** — resolve the parent and authorize against it:

```php
private function parent(Request $request, string $type, int $id): Model
{
    $cfg  = config("attachments.types.$type");
    abort_if($cfg === null, 404);

    $model = $cfg['model'];                       // e.g. Invoice::class
    $record = $model::findOrFail($id);

    abort_unless($request->user()->can('view', $record), 403);
    return $record;
}
```

and for `destroy()`, additionally `abort_unless($request->user()->can('update', $record) || $attachment->uploaded_by === $request->user()->id, 403)`.

Minimum viable patch if you want to keep the current design: add `abort_unless($attachment->uploaded_by === $request->user()->id || $request->user()->hasRole('super-admin'), 403)` to `show()`/`destroy()`, and drop `'demo' => ['can' => null]`.

---

### AU-1 · A role manager can grant themselves every permission · `RoleController.php:72-84`

`RoleRequest` accepts **any** permission name in the database, and `update()` syncs them onto the role. If the actor holds that role, they escalate.

**Proven** (feature test, then reverted): user holding only `settings.roles.update` → `PUT /settings/roles/{own role}` with all permissions → `AFTER perms=10`, `can users.update: true`, `can roles.delete: true`.

**Fix** — two rules: you cannot grant what you do not hold, and you cannot change a role that contains you.

```php
$actor = $request->user();

// 1. cannot edit a role you belong to
abort_if($role->users()->whereKey($actor->id)->exists(), 403);

// 2. cannot grant permissions you don't have
if (! $actor->hasRole('super-admin')) {
    $extra = array_diff($data['permissions'] ?? [], $actor->getAllPermissions()->pluck('name')->all());
    abort_if($extra !== [], 403, 'You cannot grant permissions you do not hold.');
}
```

### AU-2 · A user manager can assign themselves any role · `UserController.php:91-110`

`UserRequest::after()` blocks assigning `super-admin`, but nothing else. A user with `settings.users.update` can put themselves into any other role.

**Proven:** user with only `settings.users.update` → `PUT /settings/users/{self}` with `roles: ['powerful']` → `roles: powerful | perms: 10`.

**Fix:** same "cannot exceed your own authority" rule for roles, plus an explicit self-guard:

```php
if ($target?->is($actor) && ! $actor->hasRole('super-admin')) {
    abort_if(array_diff($roles, $actor->getRoleNames()->all()) !== [], 403);
}
```

### ON-1 · Fresh install is unusable · `composer.json` `setup` script

`setup` runs `migrate` but never `db:seed`. `super-admin` only exists inside `PermissionSeeder`, and `CreateAdmin` calls `$user->assignRole('super-admin')` → `RoleDoesNotExist` on a clean checkout.

**Fix:** add `"@php artisan db:seed --force"` to the `setup` script, and make the command self-healing:

```php
Role::findOrCreate('super-admin', 'web');
$user->assignRole('super-admin');
```

---

## P1 — High

### US-1 · A partial update silently disables the account · `UserController.php:98`

`'is_active' => $request->boolean('is_active')` — absent means `false`. Validation only says `boolean`, not `required`.

**Proven:** `PUT /settings/users/{id}` with just `name` + `email` → `is_active now: false`. Any API client, import script, or future mobile app that does a partial update locks the user out.

**Fix:** `'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $user->is_active`.

### US-2 · `is_active` cannot be set on create, and "unknown" is treated as disabled · `User.php:15`, `EnsureUserActive.php:15`

`#[Fillable(['name','email','password'])]` silently drops `is_active` from `create()`. `EnsureUserActive` then reads `!$user->is_active` — and an unloaded attribute is `null`, which is truthy-negated into "disabled".

**Proven:**

```
fresh instance is_active attr: NULL
GET /profile with FRESH instance => 302 /login
GET /profile after REFRESH      => 200
```

So `Auth::login(User::create([...]))` logs the user straight back out. It also means `User::create(['is_active' => false])` silently creates an **active** user — a security-relevant silent failure.

**Fix:** add `is_active` to the fillable list (or use `forceCreate`), and make the middleware defensive:

```php
if ($user && $user->is_active === false) { ... }
```

### AUTH-1 · Login throttle is bypassed by rotating the email · `CoreServiceProvider.php:42-43`

```php
Limit::perMinute(5)->by(strtolower($email) . '|' . $r->ip());
```

A new email = a new bucket = a new 5 attempts. **Proven:** 8 attempts with 8 different emails, none throttled. Each failure also writes an `audit_logs` row — so this is both a brute-force path and a cheap write-amplification DoS.

**Fix:** add a second, IP-only limiter and layer them:

```php
RateLimiter::for('login', fn (Request $r) => [
    Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()),
    Limit::perMinute(20)->by($r->ip()),
]);
```

Also consider only logging `auth.failed` after N attempts, or moving it to a queue.

### AT-2 · Uploads are unvalidated, uncapped, and never cleaned up · `AttachmentController.php:38-62`

- `id` is accepted without checking the parent record exists → orphans (`attachable_id=999` succeeded in testing).
- No per-user or global quota; 30 req/min × 10 MB = 300 MB/min per user.
- Deleting the parent record never deletes its files.
- No `visibility` is passed to `store()`, so it inherits the disk default rather than being explicit.

**Fix:** resolve the parent model (see AT-1), add `'attachable' => ['exists:...']`-style validation, cap total bytes per user, and add a `Attachment::where(...)->delete()` cleanup in the parent's `deleting` observer (or a `Prunable` model).

### AUTH-2 · No password reset, no email verification, no 2FA, no lockout

`routes/web.php` exposes only `login`, `logout`, `profile`. A user who forgets their password must ask a super-admin to set a new one. For a SaaS starter kit this is the single largest product gap — and the reason you cannot hand this to real customers.

**Recommendation:** `php artisan make:auth`-style password broker (`Password::sendResetLink`), `MustVerifyEmail` on the model, and TOTP via `pragmarx/google2fa` for super-admins at minimum.

### SEC-1 · No security headers; production cookie flags are unset

No CSP, `X-Frame-Options`, `Referrer-Policy`, or HSTS anywhere. `SESSION_ENCRYPT=false` and `SESSION_SECURE_COOKIE` is unset in `.env`.

**Fix:** a small middleware appended to the `web` group:

```php
$response->headers->set('X-Frame-Options', 'DENY');
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
$response->headers->set('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'");
```

Plus, in production `.env`: `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `APP_DEBUG=false`.

---

## P2 — Medium (backend)


| ID | Issue | Where | Fix |
|---|---|---|---|
| TQ-1 | `count(*)` runs on **every** cursor page even though the total never changes; `per_page` allows 2500 | `TableQuery.php:27-32` | Only count when `cursor` is absent; cap `per_page` at 500 |
| TQ-2 | `$map[$config['default']]` is unguarded — a config without that key throws an undefined-index error | `TableQuery.php:59` | `$map[$config['default']] ?? 'id'` |
| PF-1 | `protected $with = ['roles.permissions','permissions']` eager-loads 3 relations on **every** user fetch, including `Auth::user()` on each request | `User.php:21` | Remove `$with`; eager-load explicitly where needed |
| PF-2 | `Model::preventLazyLoading(!production)` is off in production, so N+1s become silent | `CoreServiceProvider.php:37` | Keep it on everywhere and fix the fallout |
| AU-3 | Audit log grows forever, no pruning, no index on `ip` | `audit_logs` | `Model::prunable()` with 180-day retention + scheduled `model:prune` |
| SEED-1 | `syncPermissions()` only runs when the role `wasRecentlyCreated` — new module permissions are never granted to existing `manager`/`viewer` | `PermissionSeeder.php:44-48` | Use `syncPermissions` on every run for the default roles, or gate with an explicit `--refresh-defaults` flag |
| TX-1 | `User::forceCreate` + `syncRoles`, `Role::create` + `syncPermissions` are not wrapped in a transaction | `UserController.php:60-66`, `RoleController.php:48-49` | `DB::transaction(fn () => ...)` |
| LOG-1 | `request()->ip()` returns the load-balancer IP behind a proxy → wrong audit data and a shared rate-limit bucket | `Audit.php:23` | Configure `TrustProxies` with `at: '*'` (or your LB ranges) |
| CACHE-1 | `SettingsStore` caches forever; correctness depends on every node sharing one cache store | `SettingsStore.php:14` | Fine for Redis/database; document it, or add a `settings.version` key |
| TEST-1 | 2 example tests, 1 failing, no CI workflow, `pint --test` fails on ~25 files (mixed CRLF/LF) | repo root | `pint` once, commit, add a GitHub Actions job running `pint --test` + `php artisan test` |

---

## P3 — UI / UX

### UI-1 · The app has zero responsive breakpoints — **the biggest UI finding**

`grep -r "sm:\|md:\|lg:\|xl:" resources/js/` → **0 matches**. Not one. The shell is a fixed desktop layout: a `h-12` header with a fixed `w-64` search box, horizontal module tabs with no overflow handling, and `ListView` grids with fixed `fr` column widths.

Below ~1100px the header overflows and the table squeezes to unreadable. On a phone it is unusable. For a SaaS starter kit in 2026 that is a blocker, not a nitpick.

**Recommendation:**
- Collapse the module nav into a hamburger/drawer under `md`.
- Hide the `w-64` search box under `md`, keep Ctrl-K.
- In `ListView`, switch to stacked cards under `md` (the `#cell-*` slots already give you the labels).
- Add `overflow-x-auto` to `SubNav` as an immediate stop-gap.

### UI-2 · Buttons and nav links have no visible focus ring · `resources/css/app.css:46,69`

`.field:focus-visible` exists; `.btn` and `.nav-btn` have none. Keyboard users cannot see where they are. **Fix:**

```css
.btn:focus-visible, .nav-btn:focus-visible {
  @apply outline-none ring-2 ring-accent ring-offset-1;
}
```

### UI-3 · "Select all" only selects the loaded page — and the bulk action silently obeys it

`ListView.toggleAll()` builds the set from `props.rows` (the virtualized, cursor-paginated slice). The aria-label admits it ("Select all loaded rows"), but the UI does not. With 2 500 rows loaded and 40 000 total, "Disable 2500 users" looks like "Disable everything".

**Fix:** show `All 2,500 loaded rows selected · Select all 40,000` and pass an explicit `all: true` flag to the bulk endpoint.

### UI-4 · No unsaved-changes guard

`Users/Form.vue` and `Roles/Form.vue` show a "Not Saved" badge and a Discard button, but navigating away (or Cmd-clicking a nav item) silently drops the edits. **Fix:** `onBeforeRouteLeave`-equivalent via `router.on('before', ...)` checking `form.isDirty`, or a `beforeunload` listener.

### UI-5 · Login page gaps · `Auth/Login.vue`

- No **Forgot password** link (see AUTH-2).
- No show/hide password toggle.
- No caps-lock warning.
- `const flash = usePage().props.flash` is captured non-reactively at setup — it works only because the page remounts. Use `usePage().props.flash` inside the template or a computed.

### UI-6 · Table semantics · `ListView.vue:163`

Rows carry `role="row"` but there is no `role="grid"`/`rowgroup` ancestor, and `tabindex="0"` on every row makes tabbing through 100 rows painful. There is no arrow-key navigation. Either drop the ARIA roles (a plain table is more honest) or implement the full grid pattern.

### UI-7 · Permission matrix has no search · `PermissionMatrix.vue`

With 10 permissions it's fine. With 10 modules × 6 actions it becomes a wall of checkboxes. Add a filter box and a per-group "select all", plus a summary of "N of M granted".

### UI-8 · No navigation feedback

There is no Inertia progress indicator, so a slow query looks like a dead click. One line in `app.js` (`router.on('start'/'finish')` toggling a top bar) fixes it.

### UI-9 · Hardcoded formatting

`d-m-Y H:i` and `new Intl.DateTimeFormat('en', ...)` are baked in. Store UTC, format per user locale/timezone — it's much cheaper now than after 50 screens.

### UI-10 · README is still Laravel's default

No explanation of the module system, permission naming, the `TableQuery` contract, or `config/attachments.php`. `AGENTS.md`/`CLAUDE.md` are 47 lines each. A new developer cannot extend this kit without reading the source.

---

## What is solid — don't refactor these away

- **`TableQuery` sort whitelisting.** Frontend keys map to DB columns; no user string ever reaches `orderBy`. No raw SQL anywhere in `app/` (verified: zero `DB::raw`/`whereRaw`).
- **No `v-html` anywhere** — no XSS surface in the Vue layer.
- **Attachment download hardening.** Forced `attachment` disposition + `nosniff`, so an uploaded file can't execute in the browser.
- **The private storage route is safe.** I checked the `storage/{path}` route that `'serve' => true` registers: unsigned access returns 403, and `?upload=1` *disables* the bypass rather than enabling it. Ruled out.
- **Permission caching / `Gate::before`.** Clean super-admin bypass, no per-user permission explosion.
- **Indexes.** `users.is_active`, `audit_logs(event,id)`, `audit_logs(subject_label)`, `attachments(attachable_type, attachable_id)` — the sort/filter columns are actually indexed, which is rare.
- **Dependencies.** `composer audit` and `npm audit` both clean.
- **Session regeneration on login, `current_password` check on change, password `min(10)` + `hashed` cast.** All correct.

---

## Suggested order of work

**Week 1 (blockers):** EX-1 → AT-1 → AU-1/AU-2 → ON-1. These four are hours of work, not days, and they are the difference between "starter kit" and "liability".

**Week 2 (hardening):** US-1/US-2 → AUTH-1 → AT-2 → SEC-1 → TX-1. Add the first real feature tests while you're in there: one per P0 finding, written to *fail* before the fix.

**Week 3 (product + polish):** AUTH-2 (password reset) → UI-1 (responsive shell) → UI-2/UI-3 → CI with Pint + tests.

---

## Appendix — how each P0/P1 finding was verified

| ID | Method | Result |
|---|---|---|
| EX-1 | `php artisan test`; kernel dispatch of `/examples/forbidden` and `/nope` | `TypeError ... must be of type Response` |
| AT-1 | Logged in as a 0-permission user, called `index`/`show`/`destroy` on another user's attachment | `200` / `200` / `204`, row deleted |
| AU-1 | Feature test: role with only `settings.roles.update` PUTs all permissions onto itself | 10 permissions gained |
| AU-2 | Feature test: user with only `settings.users.update` assigns self `powerful` | 10 permissions gained |
| US-1 | Feature test: `PUT /settings/users/{id}` without `is_active` | `is_active` → `false` |
| US-2 | Feature test: `actingAs($freshInstance)` vs `actingAs($instance->fresh())` | `302 /login` vs `200` |
| AUTH-1 | 8 logins, 8 distinct emails | 0 × 429 |
| Storage route | Unsigned `GET /storage/...` and `?upload=1` | both `403` — **not** vulnerable |
| Deps | `composer audit`, `npm audit --omit=dev` | 0 advisories |

All probe scripts and temporary test files were removed afterwards; the database was left exactly as found.
