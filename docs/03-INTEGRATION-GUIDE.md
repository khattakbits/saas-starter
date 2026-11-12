# Example Product Integration — MizaanbitsERP

**Yeh document product app (satellite) side ka poora kaam hai.** Control plane ki taraf se sirf ek cheez chahiye: product register + `client_id` / `client_secret` / `signing_secret`.

**Assumption:** product app KhattakBits starter kit ka clone hai — yani `app/Core/*` (Audit, Mailer, Documents, CSP, RBAC) already maujood hai. Sirf SSO + subscription layer add ho raha hai.

---

## 0. Integration ka poora map

```
Product app mein 6 naye hisse:
├── config/khattakbits.php                  ← settings
├── app/Kb/                                 ← naya namespace (Core se alag)
│   ├── KbOAuth.php                         ← authorize URL, token exchange, PKCE
│   ├── KbClient.php                        ← resolve / entitlements / renew-link
│   ├── KbUser.php                          ← local identity mapping (model)
│   ├── Entitlement.php                     ← ::has() / ::limit() helper
│   ├── TenantProvisioner.php               ← tenant create/map
│   └── Http/
│       ├── KbCallbackController.php        ← OAuth callback
│       └── KbWebhookController.php         ← HMAC verify + dispatch
├── app/Http/Middleware/
│   ├── EnsureKbSession.php                 ← blocked_at check (instant lockout)
│   └── EnsureSubscriptionActive.php        ← expiry/grace guard
├── app/Jobs/Kb/
│   ├── SyncEntitlements.php
│   └── HandleKbWebhook.php
├── database/migrations/ (5 naye)
└── routes/web.php (+ billing routes)
```

**Kyun `app/Kb/` aur `app/Core/` alag:** `app/Core` starter kit ka shared kernel hai — jab aap naya product banayenge, `Core` same rahega magar `Kb` ka config/product key badlega. Isliye `Kb` ko `Core` mein na milayein, warna starter-kit updates merge karte waqt conflict hoga.

---

## 1. Step 1 — KhattakBits admin mein product register karein

### 1.1 Catalog entry

`https://khattakbits.com/admin/products/create`:

| Field | Value |
|---|---|
| Key | `mizaanbitserp` |
| Name | Mizaanbits ERP |
| Domain | `mizaanbitserp.com` |
| Callback path | `/auth/kb/callback` |
| Webhook URL | `https://mizaanbitserp.com/webhooks/khattakbits` |
| Status | active |

### 1.2 Modules / features / doctypes / reports

```json
{
  "modules": [
    {"key": "accounting",  "label": "Accounting",  "enabled": true},
    {"key": "inventory",   "label": "Inventory",   "enabled": true},
    {"key": "crm",         "label": "CRM",         "enabled": true},
    {"key": "payroll",     "label": "Payroll",     "enabled": false}
  ],
  "features": [
    {"key": "module.accounting",          "label": "Accounting module",    "type": "boolean"},
    {"key": "module.inventory",           "label": "Inventory module",     "type": "boolean"},
    {"key": "module.crm",                 "label": "CRM module",           "type": "boolean"},
    {"key": "module.payroll",             "label": "Payroll module",       "type": "boolean"},
    {"key": "limit.users",                "label": "Max users",            "type": "quota"},
    {"key": "limit.invoices_per_month",   "label": "Invoices / month",     "type": "quota"},
    {"key": "feature.multi_currency",     "label": "Multi-currency",       "type": "boolean"},
    {"key": "feature.api_access",         "label": "REST API access",      "type": "boolean"}
  ],
  "doctypes": [
    {"key": "sales_invoice",  "label": "Sales Invoice",  "module_key": "accounting"},
    {"key": "purchase_invoice","label": "Purchase Invoice","module_key": "accounting"},
    {"key": "stock_entry",    "label": "Stock Entry",    "module_key": "inventory"}
  ],
  "reports": [
    {"key": "trial_balance",  "label": "Trial Balance",  "module_key": "accounting"},
    {"key": "stock_ledger",   "label": "Stock Ledger",   "module_key": "inventory"}
  ]
}
```

### 1.3 Plans

| Plan code | Price | Interval | Trial | Entitlements |
|---|---|---|---|---|
| `starter` | PKR 3,000 | month | 14 d | accounting ✅, inventory ❌, crm ❌, users 5, invoices 500 |
| `business` | PKR 9,000 | month | 14 d | accounting ✅, inventory ✅, crm ✅, users 25, invoices 5000 |
| `enterprise` | PKR 25,000 | month | 0 | sab ✅, users 100, invoices unlimited, multi_currency ✅, api ✅ |

### 1.4 OAuth client + secrets

Save karte waqt screen **ek dafa** dikhayegi:

```
client_id      : kb_live_mizaan_7f3a9c2e1b8d4056
client_secret  : kbs_9d4e...  (sirf ek dafa — copy kar lein)
signing_secret : whs_2c8f...  (webhook HMAC)
redirect_uris  : https://mizaanbitserp.com/auth/kb/callback
scopes         : openid profile email subscription entitlements tenant roles
```

Yeh teen values product ke `.env` mein jayengi. **Kabhi git mein commit na karein.**

---

## 2. Step 2 — Product app config

`config/khattakbits.php`:

```php
<?php

return [
    'base_url'    => env('KB_BASE_URL', 'https://khattakbits.com'),
    'product_key' => env('KB_PRODUCT_KEY', 'mizaanbitserp'),

    'oauth' => [
        'client_id'     => env('KB_CLIENT_ID'),
        'client_secret' => env('KB_CLIENT_SECRET'),
        'redirect_uri'  => env('KB_REDIRECT_URI'),
        'scopes'        => env('KB_SCOPES', 'openid profile email subscription entitlements tenant roles'),
        'authorize_path' => '/oauth/authorize',
        'token_path'     => '/oauth/token',
        'userinfo_path'  => '/oauth/userinfo',
    ],

    'webhook' => [
        'secret'       => env('KB_WEBHOOK_SECRET'),
        'tolerance'    => 300,     // seconds — replay window
        'path'         => '/webhooks/khattakbits',
    ],

    'api' => [
        'resolve_path'    => '/api/v1/subscriptions/resolve',
        'entitlements_path' => '/api/v1/entitlements',
        'renew_link_path' => '/api/v1/subscriptions/renew-link',
        'timeout'         => 5,
        'cache_ttl'       => (int) env('KB_RESOLVE_CACHE_TTL', 60),
        'offline_grace_hours' => (int) env('KB_OFFLINE_GRACE_HOURS', 72),
    ],

    'enforce_subscription' => (bool) env('KB_ENFORCE_SUBSCRIPTION', true),

    // Global (KhattakBits) role → local product role
    'role_map' => [
        'owner'   => 'admin',
        'admin'   => 'admin',
        'manager' => 'manager',
        'member'  => 'staff',
        'viewer'  => 'viewer',
    ],

    // In routes par subscription guard nahi lagega
    'billing_exempt' => [
        'billing.*',
        'auth.kb.*',
        'webhooks.khattakbits',
        'logout',
    ],
];
```

---

## 3. Step 3 — Migrations (5 tables)

`database/migrations/2026_11_15_100000_create_kb_integration_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Local mapping: KhattakBits user ↔ local user ────────────────────
        Schema::create('kb_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kb_sub', 64)->unique();        // stable global id
            $table->unsignedBigInteger('kb_token_version')->default(0);
            $table->timestamp('blocked_at')->nullable();    // user.disabled webhook
            $table->string('block_reason', 120)->nullable();
            $table->json('claims')->nullable();             // last userinfo
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('blocked_at');
        });

        // ── Subscription snapshot (control plane = source of truth) ─────────
        Schema::create('kb_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_key', 60);
            $table->string('subscription_id', 64)->nullable();
            $table->string('status', 20)->default('unknown'); // trialing|active|past_due|grace|expired|cancelled|suspended
            $table->string('plan_code', 40)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->json('payload')->nullable();
            $table->string('etag', 80)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_key']);
            $table->index(['status', 'period_end']);
        });

        // ── Entitlements (denormalized for fast checks) ─────────────────────
        Schema::create('kb_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->boolean('enabled')->default(false);
            $table->bigInteger('limit_value')->nullable();   // null = unlimited
            $table->string('version', 40)->nullable();       // entitlements_hash
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        // ── Webhook idempotency ─────────────────────────────────────────────
        Schema::create('kb_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();       // X-KB-Event-Id
            $table->string('event', 60);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('received_at')->useCurrent();
        });

        // ── Tenant mapping ──────────────────────────────────────────────────
        Schema::create('kb_tenants', function (Blueprint $table) {
            $table->id();
            $table->string('kb_tenant_id', 64)->unique();
            $table->string('slug', 80)->unique();
            $table->string('name', 190);
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('kb_tenant_user', function (Blueprint $table) {
            $table->foreignId('kb_tenant_id')->constrained('kb_tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_role', 40)->default('member');
            $table->timestamps();

            $table->primary(['kb_tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_tenant_user');
        Schema::dropIfExists('kb_tenants');
        Schema::dropIfExists('kb_webhook_events');
        Schema::dropIfExists('kb_entitlements');
        Schema::dropIfExists('kb_subscriptions');
        Schema::dropIfExists('kb_users');
    }
};
```

---

## 4. Step 4 — OAuth2 client (PKCE)

`app/Kb/KbOAuth.php`:

```php
<?php

namespace App\Kb;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class KbOAuth
{
    /** 43–128 chars, unreserved — PKCE spec */
    public function generateVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    public function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function authorizationUrl(string $state, string $verifier): string
    {
        $query = http_build_query([
            'response_type'         => 'code',
            'client_id'             => config('khattakbits.oauth.client_id'),
            'redirect_uri'          => config('khattakbits.oauth.redirect_uri'),
            'scope'                 => config('khattakbits.oauth.scopes'),
            'state'                 => $state,
            'code_challenge'        => $this->challenge($verifier),
            'code_challenge_method' => 'S256',
        ]);

        return config('khattakbits.base_url')
            .config('khattakbits.oauth.authorize_path')
            .'?'.$query;
    }

    /** @return array{access_token:string,refresh_token:?string,id_token:?string,expires_in:int,scope:string} */
    public function exchangeCode(string $code, string $verifier): array
    {
        $response = Http::asForm()
            ->timeout(10)
            ->acceptJson()
            ->post(config('khattakbits.base_url').config('khattakbits.oauth.token_path'), [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('khattakbits.oauth.client_id'),
                'client_secret' => config('khattakbits.oauth.client_secret'),
                'code'          => $code,
                'redirect_uri'  => config('khattakbits.oauth.redirect_uri'),
                'code_verifier' => $verifier,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Token exchange failed: '.$response->body());
        }

        return $response->json();
    }

    /** @return array<string,mixed> */
    public function userinfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->acceptJson()
            ->get(config('khattakbits.base_url').config('khattakbits.oauth.userinfo_path'));

        if ($response->failed()) {
            throw new RuntimeException('Userinfo failed: '.$response->status());
        }

        return $response->json();
    }

    public function refresh(string $refreshToken): array
    {
        $response = Http::asForm()
            ->timeout(10)
            ->acceptJson()
            ->post(config('khattakbits.base_url').config('khattakbits.oauth.token_path'), [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('khattakbits.oauth.client_id'),
                'client_secret' => config('khattakbits.oauth.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Refresh failed: '.$response->status());
        }

        return $response->json();
    }
}
```

---

## 5. Step 5 — API client (subscription resolve)

`app/Kb/KbClient.php`:

```php
<?php

namespace App\Kb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class KbClient
{
    public function __construct(private KbOAuth $oauth) {}

    /**
     * Subscription + entitlements resolve karta hai. Cache-first, ETag-aware.
     *
     * @return array<string,mixed>
     */
    public function resolve(string $accessToken, string $etag = null): array
    {
        $cacheKey = 'kb:resolve:'.config('khattakbits.product_key');

        $response = Http::withToken($accessToken)
            ->timeout(config('khattakbits.api.timeout'))
            ->acceptJson()
            ->withHeaders($etag ? ['If-None-Match' => $etag] : [])
            ->get(config('khattakbits.base_url').config('khattakbits.api.resolve_path'), [
                'product' => config('khattakbits.product_key'),
            ]);

        if ($response->status() === 304) {
            return Cache::get($cacheKey, []);
        }

        if ($response->failed()) {
            Log::warning('KB resolve failed', ['status' => $response->status()]);
            return Cache::get($cacheKey, []);
        }

        $data = $response->json('data', []);
        Cache::put($cacheKey, $data, config('khattakbits.api.cache_ttl'));

        return $data;
    }

    /** @return array{checkout_url:string,expires_at:string}|null */
    public function renewLink(string $accessToken, string $planCode, string $returnUrl): ?array
    {
        $response = Http::withToken($accessToken)
            ->timeout(config('khattakbits.api.timeout'))
            ->acceptJson()
            ->post(config('khattakbits.base_url').config('khattakbits.api.renew_link_path'), [
                'product'    => config('khattakbits.product_key'),
                'plan'       => $planCode,
                'return_url' => $returnUrl,
            ]);

        return $response->successful() ? $response->json('data') : null;
    }

    public function entitlements(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(config('khattakbits.api.timeout'))
            ->acceptJson()
            ->get(config('khattakbits.base_url').config('khattakbits.api.entitlements_path'), [
                'product' => config('khattakbits.product_key'),
            ]);

        return $response->successful() ? $response->json('data', []) : [];
    }
}
```

---

## 6. Step 6 — Callback controller (login flow ka dil)

`app/Kb/Http/KbCallbackController.php`:

```php
<?php

namespace App\Kb\Http;

use App\Kb\Entitlement;
use App\Kb\KbClient;
use App\Kb\KbOAuth;
use App\Kb\TenantProvisioner;
use App\Models\User;
use App\Core\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

final class KbCallbackController
{
    public function __construct(
        private KbOAuth $oauth,
        private KbClient $client,
        private TenantProvisioner $tenants,
        private Entitlement $entitlements,
    ) {}

    /** GET /auth/kb/redirect — user ko KhattakBits par bhejta hai */
    public function redirect(Request $request)
    {
        $verifier = $this->oauth->generateVerifier();
        $state    = Str::random(48);

        $request->session()->put('kb_oauth', [
            'state'    => $state,
            'verifier' => $verifier,
            'intended' => $request->query('intended', '/dashboard'),
        ]);

        return redirect()->away($this->oauth->authorizationUrl($state, $verifier));
    }

    /** GET /auth/kb/callback — code aa jata hai */
    public function callback(Request $request)
    {
        $payload = $request->session()->pull('kb_oauth');

        // ── 1. State verify (CSRF on SSO) ───────────────────────────────────
        if (! $payload || ! $request->filled('state')
            || ! hash_equals($payload['state'], (string) $request->query('state'))) {
            return redirect('/login')->with('error', 'SSO state mismatch. Please try again.');
        }

        if ($request->filled('error')) {
            return redirect('/login')->with('error', 'SSO cancelled: '.$request->query('error'));
        }

        // ── 2. Code exchange ────────────────────────────────────────────────
        try {
            $tokens = $this->oauth->exchangeCode(
                (string) $request->query('code'),
                $payload['verifier']
            );
            $claims = $this->oauth->userinfo($tokens['access_token']);
        } catch (\Throwable $e) {
            report($e);

            return redirect('/login')->with('error', 'SSO failed. Please try again.');
        }

        // ── 3. Global user disabled? ────────────────────────────────────────
        if (($claims['kb_status'] ?? 'active') !== 'active') {
            return redirect('/login')->with('error', 'Your account has been disabled.');
        }

        // ── 4. Local user provision / map ───────────────────────────────────
        $user = DB::transaction(function () use ($claims, $tokens) {
            $user = User::query()->firstOrNew(['email' => $claims['email']]);
            $user->name = $claims['name'] ?? $user->name ?? $claims['email'];
            $user->email_verified_at ??= now();
            if (! $user->exists) {
                $user->password = Str::random(64);   // SSO-only account
            }
            $user->is_active = true;
            $user->save();

            $user->kbIdentity()->updateOrCreate([], [
                'kb_sub'           => $claims['sub'],
                'kb_token_version' => (int) ($claims['kb_token_version'] ?? 0),
                'blocked_at'       => null,
                'claims'           => $claims,
                'last_synced_at'   => now(),
            ]);

            // ── 5. Subscription snapshot + entitlements ─────────────────────
            $resolved = $this->client->resolve($tokens['access_token']);
            $this->entitlements->syncFrom($user, $resolved, $tokens);

            // ── 6. Tenant map + role ────────────────────────────────────────
            $this->tenants->mapFrom($user, $claims, $resolved);

            return $user;
        });

        // ── 7. Local login ──────────────────────────────────────────────────
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        Audit::log('auth.login', $user, $user->email, ['via' => 'khattakbits_sso']);

        return redirect($payload['intended'] ?: '/dashboard');
    }
}
```

**Product `User` model mein relation:**

```php
// app/Models/User.php
public function kbIdentity(): HasOne
{
    return $this->hasOne(\App\Kb\KbUser::class);
}

public function kbSubscription(): HasOne
{
    return $this->hasOne(\App\Kb\KbSubscription::class);
}
```

---

## 7. Step 7 — Entitlement helper

`app/Kb/Entitlement.php`:

```php
<?php

namespace App\Kb;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class Entitlement
{
    /** Per-request memo — ek request mein 20 checks bhi 1 query */
    private array $memo = [];

    public function has(string $key): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return (bool) $this->value($user, $key)['enabled'];
    }

    /** null = unlimited */
    public function limit(string $key): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return 0;
        }

        return $this->value($user, $key)['limit'];
    }

    public function allows(string $key, int $usage): bool
    {
        $limit = $this->limit($key);

        return $limit === null || $usage < $limit;
    }

    /** Blade/Vue ke liye poora map */
    public function all(?User $user = null): array
    {
        $user ??= auth()->user();
        if (! $user) {
            return [];
        }

        return Cache::remember(
            "kb:ent:{$user->id}",
            now()->addMinutes(5),
            fn () => $user->kbEntitlements()->get()
                ->mapWithKeys(fn ($e) => [$e->key => $e->enabled ? ($e->limit_value ?? true) : false])
                ->all()
        );
    }

    /** @return array{enabled:bool,limit:?int} */
    private function value(User $user, string $key): array
    {
        $this->memo[$user->id] ??= $this->all($user);

        $raw = $this->memo[$user->id][$key] ?? false;

        return [
            'enabled' => $raw !== false,
            'limit'   => is_int($raw) ? $raw : null,
        ];
    }

    /**
     * Resolve response se local snapshot likhta hai.
     *
     * @param array<string,mixed> $resolved
     */
    public function syncFrom(User $user, array $resolved, array $tokens = []): void
    {
        if (empty($resolved)) {
            return;   // control plane down — purana snapshot rakhein (offline fallback)
        }

        $sub = $resolved['subscription_id'] ?? null;

        $user->kbSubscription()->updateOrCreate(
            ['product_key' => config('khattakbits.product_key')],
            [
                'subscription_id' => $sub,
                'status'          => $resolved['status'] ?? 'unknown',
                'plan_code'       => $resolved['plan']['code'] ?? null,
                'period_start'    => $resolved['period']['start'] ?? null,
                'period_end'      => $resolved['period']['end'] ?? null,
                'grace_until'     => $resolved['period']['grace_until'] ?? null,
                'payload'         => $resolved,
                'etag'            => $resolved['etag'] ?? null,
                'synced_at'       => now(),
            ]
        );

        $rows = [];
        foreach (($resolved['entitlements'] ?? []) as $key => $value) {
            $rows[] = [
                'user_id'     => $user->id,
                'key'         => $key,
                'enabled'     => $value !== false,
                'limit_value' => is_int($value) ? $value : null,
                'version'     => $resolved['etag'] ?? null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        if ($rows) {
            \App\Kb\Models\KbEntitlement::query()->upsert(
                $rows,
                ['user_id', 'key'],
                ['enabled', 'limit_value', 'version', 'updated_at']
            );
        }

        Cache::forget("kb:ent:{$user->id}");
    }
}
```

**Usage:**

```php
// Controller / policy mein
if (! app(Entitlement::class)->has('module.accounting')) {
    abort(403, 'Accounting module aap ke plan mein shamil nahi.');
}

// Quota check
$used = Invoice::whereMonth('created_at', now()->month)->count();
abort_unless(app(Entitlement::class)->allows('limit.invoices_per_month', $used), 422,
    'Monthly invoice limit reached. Please upgrade.');

// Blade / Inertia
'can' => [
    'accounting' => app(Entitlement::class)->has('module.accounting'),
    'users'      => app(Entitlement::class)->limit('limit.users'),
],
```

**Vue mein** — `HandleInertiaRequests::share()` mein add karein:

```php
'entitlements' => fn () => app(\App\Kb\Entitlement::class)->all(),
```

```vue
<!-- resources/js/core/composables/useEntitlement.js -->
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useEntitlement() {
  const page = usePage()
  const can = (key) => page.props.entitlements?.[key] !== false && page.props.entitlements?.[key] !== undefined
  const limit = (key) => (typeof page.props.entitlements?.[key] === 'number' ? page.props.entitlements[key] : null)
  return { can, limit, all: computed(() => page.props.entitlements ?? {}) }
}
```

---

## 8. Step 8 — Middleware (2 layers)

### 8.1 `EnsureKbSession` — instant global lockout

`app/Http/Middleware/EnsureKbSession.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EnsureKbSession
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $identity = $user->kbIdentity;

        // Control plane ne user disable kiya — webhook ne blocked_at set kiya
        if ($identity?->blocked_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Account disabled.'], 401)
                : redirect('/login')->with('error', 'Your account has been disabled.');
        }

        // Token version mismatch = control plane ne tokens revoke kiye
        // (webhook miss ho gaya ho tab bhi yeh pakadta hai)
        if ($identity && $identity->kb_token_version < (int) cache()->get("kb:tv:{$identity->kb_sub}", 0)) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect('/login')->with('error', 'Session revoked. Please sign in again.');
        }

        return $next($request);
    }
}
```

`bootstrap/app.php` mein register:

```php
$middleware->web(append: [
    HandleInertiaRequests::class,
    SecurityHeaders::class,
    \App\Core\Http\Middleware\EnsureUserActive::class,
    \App\Http\Middleware\EnsureKbSession::class,
]);
```

> **Note:** `EnsureUserActive` (starter kit) local `is_active` dekhta hai. `EnsureKbSession` global `blocked_at` dekhta hai. **Dono chahiye** — local admin bhi user band kar sakta hai, control plane bhi.

### 8.2 `EnsureSubscriptionActive` — expiry guard

`app/Http/Middleware/EnsureSubscriptionActive.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Kb\Entitlement;
use App\Kb\KbClient;
use Closure;
use Illuminate\Http\Request;

final class EnsureSubscriptionActive
{
    public function __construct(private KbClient $client, private Entitlement $entitlements) {}

    public function handle(Request $request, Closure $next)
    {
        if (! config('khattakbits.enforce_subscription')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Billing / SSO / webhook routes exempt
        foreach (config('khattakbits.billing_exempt') as $pattern) {
            if ($request->routeIs($pattern)) {
                return $next($request);
            }
        }

        $sub = $user->kbSubscription;

        // ── Control plane down? Offline fallback ────────────────────────────
        if (! $sub) {
            return $this->deny($request, 'No subscription found for this product.');
        }

        $staleHours = $sub->synced_at?->diffInHours(now()) ?? 999;

        if ($staleHours > config('khattakbits.api.offline_grace_hours')) {
            // 72 ghante se zyada purana snapshot — read-only mode
            $request->session()->flash('banner', 'Billing service unreachable. Read-only mode.');
            $request->attributes->set('read_only', true);
        }

        // ── Status machine ──────────────────────────────────────────────────
        $status = $sub->status;

        if (in_array($status, ['active', 'trialing'], true)) {
            return $next($request);
        }

        if ($status === 'past_due') {
            $request->session()->flash('banner', 'Payment pending. Please update billing.');

            return $next($request);   // grace: full access, banner only
        }

        if ($status === 'grace' && $sub->grace_until?->isFuture()) {
            // Grace: read-only — writes block
            if (! $request->isMethod('GET')) {
                return $this->deny($request, 'Subscription expired. Renewal required to make changes.');
            }
            $request->session()->flash('banner', 'Subscription expired — read-only until '.$sub->grace_until->format('d M Y'));

            return $next($request);
        }

        return $this->deny($request, 'Subscription expired.');
    }

    private function deny(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message'      => $message,
                'renew_url'    => route('billing.expired'),
            ], 402);   // 402 Payment Required
        }

        return redirect()->route('billing.expired');
    }
}
```

---

## 9. Step 9 — Webhook handler (HMAC + idempotency)

`app/Kb/Http/KbWebhookController.php`:

```php
<?php

namespace App\Kb\Http;

use App\Jobs\Kb\HandleKbWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class KbWebhookController
{
    public function __invoke(Request $request)
    {
        // ── 1. Signature verify (raw body par — json() se pehle) ────────────
        $raw = $request->getContent();
        $ts  = (int) $request->header('X-KB-Timestamp', 0);

        if (abs(time() - $ts) > config('khattakbits.webhook.tolerance')) {
            Log::warning('KB webhook: timestamp out of window', ['ts' => $ts]);

            return response()->json(['message' => 'Stale timestamp'], 400);
        }

        $signature = $this->parseSignature((string) $request->header('X-KB-Signature'));
        $expected  = hash_hmac('sha256', $ts.'.'.$raw, (string) config('khattakbits.webhook.secret'));

        if (! hash_equals($expected, $signature)) {
            Log::warning('KB webhook: bad signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // ── 2. Idempotency ──────────────────────────────────────────────────
        $eventId = (string) $request->header('X-KB-Event-Id');
        if ($eventId === '') {
            return response()->json(['message' => 'Missing event id'], 400);
        }

        $event = $request->header('X-KB-Event', 'unknown');
        $payload = json_decode($raw, true) ?: [];

        $inserted = DB::table('kb_webhook_events')->insertOrIgnore([
            'event_id'    => $eventId,
            'event'       => $event,
            'payload'     => json_encode($payload),
            'received_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['message' => 'Duplicate'], 200);   // already processed
        }

        // ── 3. Fast ack, kaam queue mein ────────────────────────────────────
        HandleKbWebhook::dispatch($eventId, $event, $payload)->onQueue('webhooks');

        return response()->json(['message' => 'Accepted'], 202);
    }

    /** "t=1699...,v1=abc123" → "abc123" */
    private function parseSignature(string $header): string
    {
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k === 'v1' && $v) {
                return $v;
            }
        }

        return '';
    }
}
```

`app/Jobs/Kb/HandleKbWebhook.php`:

```php
<?php

namespace App\Jobs\Kb;

use App\Kb\Entitlement;
use App\Kb\TenantProvisioner;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class HandleKbWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        private string $eventId,
        private string $event,
        private array $payload,
    ) {}

    public function handle(Entitlement $entitlements, TenantProvisioner $tenants): void
    {
        try {
            match ($this->event) {
                'user.disabled'         => $this->userDisabled(),
                'user.enabled'          => $this->userEnabled(),
                'subscription.created',
                'subscription.renewed',
                'plan.changed'          => $this->subscriptionChanged(),
                'subscription.expired',
                'subscription.cancelled' => $this->subscriptionEnded(),
                'tenant.provisioned'    => $tenants->provisionFromWebhook($this->payload),
                'tenant.suspended'      => $tenants->suspend($this->payload['tenant_id'] ?? ''),
                default                 => Log::info('KB webhook: unhandled event', ['event' => $this->event]),
            };

            DB::table('kb_webhook_events')
                ->where('event_id', $this->eventId)
                ->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('kb_webhook_events')
                ->where('event_id', $this->eventId)
                ->update(['error' => $e->getMessage()]);

            throw $e;   // queue retry
        }
    }

    private function userDisabled(): void
    {
        $identity = \App\Kb\Models\KbUser::query()
            ->where('kb_sub', $this->payload['user_id'])
            ->first();

        if (! $identity) {
            return;
        }

        $identity->forceFill([
            'blocked_at'       => now(),
            'block_reason'     => $this->payload['reason'] ?? 'disabled_by_control_plane',
            'kb_token_version' => (int) ($this->payload['token_version'] ?? $identity->kb_token_version + 1),
        ])->save();

        // Sab local sessions turant kill
        DB::table('sessions')->where('user_id', $identity->user_id)->delete();
    }

    private function userEnabled(): void
    {
        \App\Kb\Models\KbUser::query()
            ->where('kb_sub', $this->payload['user_id'])
            ->update([
                'blocked_at'       => null,
                'block_reason'     => null,
                'kb_token_version' => (int) ($this->payload['token_version'] ?? 0),
            ]);
    }

    private function subscriptionChanged(): void
    {
        $identity = \App\Kb\Models\KbUser::query()
            ->where('kb_sub', $this->payload['user_id'] ?? '')
            ->first();

        if (! $identity || ! $user = User::find($identity->user_id)) {
            return;
        }

        // Fresh resolve — webhook payload partial hota hai, poora state control plane se lein
        $resolved = app(\App\Kb\KbClient::class)->resolve($this->payload['access_token'] ?? '');
        app(Entitlement::class)->syncFrom($user, $resolved);
    }

    private function subscriptionEnded(): void
    {
        $identity = \App\Kb\Models\KbUser::query()
            ->where('kb_sub', $this->payload['user_id'] ?? '')
            ->first();

        if (! $identity) {
            return;
        }

        \App\Kb\Models\KbSubscription::query()
            ->where('user_id', $identity->user_id)
            ->where('product_key', config('khattakbits.product_key'))
            ->update([
                'status'      => $this->payload['status'] ?? 'expired',
                'grace_until' => $this->payload['grace_until'] ?? null,
                'synced_at'   => now(),
            ]);

        cache()->forget("kb:ent:{$identity->user_id}");
    }
}
```

**Routes** (`routes/web.php` — **`auth` ke bahar**, kyunke webhook server-to-server hai):

```php
// Public webhook endpoint (HMAC se protected)
Route::post('/webhooks/khattakbits', \App\Kb\Http\KbWebhookController::class)
    ->name('webhooks.khattakbits')
    ->middleware('throttle:120,1');

// SSO routes
Route::middleware('guest')->group(function () {
    Route::get('/auth/kb/redirect', [\App\Kb\Http\KbCallbackController::class, 'redirect'])
        ->name('auth.kb.redirect');
    Route::get('/auth/kb/callback', [\App\Kb\Http\KbCallbackController::class, 'callback'])
        ->name('auth.kb.callback');
});
```

> **Webhook route ko `web` middleware group se `VerifyCsrfToken` exempt karna hoga** — Laravel 13 mein CSRF `web` group ka hissa hai. `bootstrap/app.php`:
> ```php
> $middleware->validateCsrfTokens(except: ['webhooks/*']);
> ```

---

## 10. Step 10 — Tenant mapping / provisioning

`app/Kb/TenantProvisioner.php`:

```php
<?php

namespace App\Kb;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantProvisioner
{
    /**
     * id_token claims + resolve response se tenant map karta hai.
     * Agar control plane ne tenant diya hai magar local record nahi — create.
     *
     * @param array<string,mixed> $claims
     * @param array<string,mixed> $resolved
     */
    public function mapFrom(User $user, array $claims, array $resolved): void
    {
        $kbTenantId = $claims['tenant_id'] ?? $resolved['tenant']['id'] ?? null;
        if (! $kbTenantId) {
            return;
        }

        $tenant = \App\Kb\Models\KbTenant::query()->firstOrCreate(
            ['kb_tenant_id' => $kbTenantId],
            [
                'slug'   => $this->uniqueSlug($resolved['tenant']['slug'] ?? $claims['email']),
                'name'   => $resolved['tenant']['name'] ?? ($claims['name'] ?? 'Workspace'),
                'status' => $resolved['tenant']['status'] ?? 'active',
                'config' => ['plan' => $resolved['plan']['code'] ?? null],
            ]
        );

        // Product role: id_token ke products[] se
        $role = collect($claims['products'] ?? [])
            ->firstWhere('key', config('khattakbits.product_key'))['role'] ?? 'member';

        $tenant->users()->syncWithoutDetaching([
            $user->id => ['product_role' => $role],
        ]);

        $this->applyLocalRole($user, $role);
    }

    /** @param array<string,mixed> $payload */
    public function provisionFromWebhook(array $payload): void
    {
        \App\Kb\Models\KbTenant::query()->updateOrCreate(
            ['kb_tenant_id' => $payload['tenant_id']],
            [
                'slug'   => $this->uniqueSlug($payload['slug'] ?? $payload['name'] ?? 'workspace'),
                'name'   => $payload['name'] ?? 'Workspace',
                'status' => 'active',
                'config' => ['plan' => $payload['plan'] ?? null],
            ]
        );
    }

    public function suspend(string $kbTenantId): void
    {
        \App\Kb\Models\KbTenant::query()
            ->where('kb_tenant_id', $kbTenantId)
            ->update(['status' => 'suspended']);
    }

    /**
     * Global role → local Spatie role.
     * Role exist na kare to create (starter kit ka permission catalog use karein).
     */
    private function applyLocalRole(User $user, string $globalRole): void
    {
        $local = config("khattakbits.role_map.{$globalRole}", 'staff');

        if (! \Spatie\Permission\Models\Role::where('name', $local)->exists()) {
            return;   // seeders ne role banaya hoga — silently skip
        }

        $user->syncRoles([$local]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'workspace';
        $original = $slug;
        $i = 1;

        while (\App\Kb\Models\KbTenant::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }
}
```

---

## 11. Step 11 — Expiry flow (product ke andar re-subscribe)

### Routes

```php
Route::middleware(['auth'])->prefix('billing')->group(function () {
    Route::get('/expired', [\App\Kb\Http\BillingController::class, 'expired'])->name('billing.expired');
    Route::post('/renew', [\App\Kb\Http\BillingController::class, 'renew'])->name('billing.renew');
    Route::get('/return', [\App\Kb\Http\BillingController::class, 'return'])->name('billing.return');
});
```

### Controller

`app/Kb/Http/BillingController.php`:

```php
<?php

namespace App\Kb\Http;

use App\Kb\Entitlement;
use App\Kb\KbClient;
use App\Kb\KbOAuth;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class BillingController
{
    public function __construct(
        private KbClient $client,
        private KbOAuth $oauth,
        private Entitlement $entitlements,
    ) {}

    public function expired(Request $request)
    {
        $sub = $request->user()->kbSubscription;

        return Inertia::render('Billing/Expired', [
            'status'     => $sub?->status ?? 'none',
            'plan'       => $sub?->plan_code,
            'period_end' => $sub?->period_end?->toIso8601String(),
            'grace_until' => $sub?->grace_until?->toIso8601String(),
            'read_only'  => $sub?->status === 'grace',
        ]);
    }

    public function renew(Request $request)
    {
        $data = $request->validate([
            'plan' => ['nullable', 'string', 'max:40'],
        ]);

        $token = $request->session()->get('kb_access_token');
        if (! $token) {
            // Token expire ho gaya — dobara SSO
            return redirect()->route('auth.kb.redirect', ['intended' => '/billing/expired']);
        }

        $link = $this->client->renewLink(
            $token,
            $data['plan'] ?? $request->user()->kbSubscription?->plan_code ?? 'business',
            route('billing.return')
        );

        if (! $link) {
            return back()->with('error', 'Billing service se checkout link nahi mila. Dobara koshish karein.');
        }

        return redirect()->away($link['checkout_url']);
    }

    public function return(Request $request)
    {
        // Checkout se wapas — fresh resolve
        $token = $request->session()->get('kb_access_token');
        $user  = $request->user();

        if ($token) {
            $resolved = $this->client->resolve($token, force: true);
            $this->entitlements->syncFrom($user, $resolved);
        }

        $status = $user->fresh()->kbSubscription?->status;

        return redirect('/dashboard')->with(
            in_array($status, ['active', 'trialing'], true) ? 'success' : 'error',
            in_array($status, ['active', 'trialing'], true)
                ? 'Subscription renewed. Welcome back!'
                : 'Payment process ho gaya magar activation pending hai. Thori der baad refresh karein.'
        );
    }
}
```

### Vue screen — `resources/js/Pages/Billing/Expired.vue`

```vue
<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({ status: String, plan: String, grace_until: String, read_only: Boolean })
const busy = ref(false)

function renew() {
  busy.value = true
  router.post('/billing/renew', {}, { onFinish: () => (busy.value = false) })
}
</script>

<template>
  <div class="mx-auto max-w-lg py-24 text-center">
    <h1 class="text-2xl font-semibold">
      {{ read_only ? 'Aap ka subscription expire ho gaya' : 'Subscription active nahi hai' }}
    </h1>

    <p class="mt-3 text-sm text-slate-600">
      <template v-if="read_only">
        Data safe hai. Renewal tak sirf padha ja sakta hai. Grace period:
        <strong>{{ grace_until }}</strong>
      </template>
      <template v-else>
        Dashboard use karne ke liye plan renew karein. Billing KhattakBits par handle hoti hai —
        card details yahan kabhi nahi aati.
      </template>
    </p>

    <button
      :disabled="busy"
      class="mt-8 rounded-lg bg-slate-900 px-6 py-3 text-sm font-medium text-white disabled:opacity-50"
      @click="renew"
    >
      {{ busy ? 'Redirecting…' : 'Renew subscription' }}
    </button>

    <p class="mt-4 text-xs text-slate-400">
      Plan: {{ plan ?? '—' }} · Status: {{ status }}
    </p>
  </div>
</template>
```

---

## 12. Step 12 — Product-scoped roles mapping

```
KhattakBits global user
        │
        │ id_token.products[] = [{ key: "mizaanbitserp", role: "admin", tenant_id: "tnt_..." }]
        ▼
kb_users (kb_sub ↔ local user_id)
        │
        ▼
kb_tenant_user (tenant_id, user_id, product_role)   ← product-scoped role
        │
        ▼
Spatie local role (admin | manager | staff | viewer)  ← actual permissions
```

**Kyun do layers:** `product_role` control plane ka concept hai (`owner`, `admin`, `member`). Spatie role local permissions ka set hai. Mapping config mein hai (`role_map`), is liye control plane naya role add kare to sirf config badalna hai — code nahi.

**Tenant-scoped permission check (jab multi-tenancy aaye):**

```php
// app/Kb/Concerns/HasTenantRoles.php
public function hasProductRole(string $role, ?int $tenantId = null): bool
{
    $tenantId ??= session('kb_tenant_id');

    return $this->kbTenants()
        ->wherePivot('product_role', $role)
        ->when($tenantId, fn ($q) => $q->where('kb_tenants.id', $tenantId))
        ->exists();
}
```

---

## 13. Step 13 — Token storage (access token kidhar rakhein)

`KbCallbackController` mein `kb_access_token` session mein rakha gaya. Production mein behtar:

```php
// Session mein sirf refresh token (encrypted), access token memory/cache mein
$request->session()->put('kb_refresh_token', encrypt($tokens['refresh_token']));
Cache::put("kb:at:{$user->id}", $tokens['access_token'], $tokens['expires_in'] - 60);
```

**Kyun:** access token 15 min ka hai — usse DB/session mein rakhne ka faida nahi. Refresh token long-lived hai, usse **encrypted** rakhein (`SESSION_ENCRYPT=true` already helps).

**Auto-refresh job** (`app/Jobs/Kb/RefreshKbToken.php`) — queue se har 10 min, sirf un users ke liye jinka token expire hone wala hai.

---

## 14. Tests (jo likhne chahiye)

```php
// tests/Feature/Kb/KbWebhookTest.php
it('rejects a webhook with a bad signature', function () {
    $this->postJson('/webhooks/khattakbits', ['event' => 'user.disabled'], [
        'X-KB-Event'     => 'user.disabled',
        'X-KB-Event-Id'  => 'evt_1',
        'X-KB-Timestamp' => time(),
        'X-KB-Signature' => 't='.time().',v1=deadbeef',
    ])->assertStatus(401);
});

it('accepts a correctly signed webhook and blocks the user', function () {
    $user = User::factory()->create();
    $user->kbIdentity()->create(['kb_sub' => 'usr_1', 'kb_token_version' => 1]);

    $body = json_encode(['user_id' => 'usr_1', 'token_version' => 2]);
    $ts = time();
    $sig = hash_hmac('sha256', $ts.'.'.$body, config('khattakbits.webhook.secret'));

    $this->call('POST', '/webhooks/khattakbits', [], [], [], [
        'HTTP_X-KB-Event'     => 'user.disabled',
        'HTTP_X-KB-Event-Id'  => 'evt_2',
        'HTTP_X-KB-Timestamp' => $ts,
        'HTTP_X-KB-Signature' => "t={$ts},v1={$sig}",
        'CONTENT_TYPE'        => 'application/json',
    ], $body)->assertStatus(202);

    expect($user->fresh()->kbIdentity->blocked_at)->not->toBeNull();
});

it('is idempotent for a duplicate event id', function () {
    // same event_id do dafa → doosri dafa 200 Duplicate, job sirf ek dafa
});
```

```php
// tests/Feature/Kb/EntitlementTest.php
it('blocks a module the plan does not include', function () {
    $user = User::factory()->create();
    $user->kbEntitlements()->create(['key' => 'module.accounting', 'enabled' => false]);

    $this->actingAs($user);
    expect(app(Entitlement::class)->has('module.accounting'))->toBeFalse();
});
```

```php
// tests/Feature/Kb/SubscriptionGuardTest.php
it('sends an expired user to the billing screen', function () {
    $user = User::factory()->create();
    $user->kbSubscription()->create(['product_key' => 'mizaanbitserp', 'status' => 'expired']);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/billing/expired');
});

it('allows read-only access during grace', function () {
    $user = User::factory()->create();
    $user->kbSubscription()->create([
        'product_key' => 'mizaanbitserp',
        'status'      => 'grace',
        'grace_until' => now()->addDays(3),
    ]);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    $this->actingAs($user)->post('/invoices', [])->assertRedirect('/billing/expired');
});
```

---

## 15. Integration checklist (MizaanbitsERP)

- [ ] Product KhattakBits admin mein register hua (`key`, `domain`, `callback`, `webhook`)
- [ ] Modules / features / doctypes / reports define hue
- [ ] 3 plans + entitlements set hue
- [ ] `client_id`, `client_secret`, `signing_secret` `.env` mein (git mein **nahi**)
- [ ] 5 migrations chal chuki (`kb_users`, `kb_subscriptions`, `kb_entitlements`, `kb_webhook_events`, `kb_tenants`)
- [ ] `config/khattakbits.php` add hua
- [ ] `/auth/kb/redirect` → KhattakBits 3D login → `/auth/kb/callback` ka poora loop kaam kar raha
- [ ] `state` mismatch test pass
- [ ] Webhook signature test pass (bad signature 401)
- [ ] Webhook idempotency test pass
- [ ] `Entitlement::has('module.accounting')` false par 403
- [ ] Expired user `/billing/expired` par jata hai
- [ ] Grace period mein GET chalta hai, POST block hota hai
- [ ] Renewal → checkout → return → dashboard unlock
- [ ] `user.disabled` webhook → user **turant** logout (session row delete)
- [ ] Tenant map hua, `product_role` → Spatie role
- [ ] `php artisan queue:work --queue=webhooks` supervisor mein chal raha
- [ ] `kb:refresh-entitlements` scheduler mein hai
- [ ] Control plane down hone par 72 ghante tak app chalta hai (snapshot se)
