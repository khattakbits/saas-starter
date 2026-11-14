# 11 — Master Roadmap

**Status:** Execution plan (design, code nahi)
**Date:** 14 Nov 2026
**Depends on:** `02` (decisions + versioning), `03` (Part D — D2/D3), `04` (CP build order §7), `05` (§8 effort), `06` (§6 effort), `07` (§12 effort), `08` (§5 effort), `09` (deployment), `10` (§11 timeline)

> **Yeh roadmap `kya`, `kis order`, aur `kitne din` ka jawab deta hai.** `kaise` ka jawab `02`–`10` mein hai — yahan koi design dohraaya nahi gaya.
>
> **Har effort number ke saath uska doc source likha hai.** Jahan doc mein number **nahi** hai, wahan `NOT ESTIMATED` likha hai — guess nahi kiya. §5 mein poora hisaab khula hua hai (kaunsa number kis se minus hua), taake aap khud verify kar sakein.

---

## 0. Method — teen rules

### 0.1 Effort sirf docs se

Yeh roadmap koi naya estimate nahi deta. Jo number `04` §7, `05` §8, `06` §6, `07` §12, `08` §5, `10` §11 mein hai, wohi yahan aaya hai — sirf **overlap hata kar** (§0.2).

| Doc | Kya deta hai | Number |
|---|---|---|
| `04` §7 | CP build, S0–S9 | 51 din |
| `05` §8 | Identity hub, I0–I9 | 27.5 din |
| `06` §6.1 | Payments Phase 1, P1.0–P1.6 | 25 din |
| `06` §6.2 | Payments Phase 2, P2.0–P2.7 | 20 din effort |
| `07` §12.1 | Tenancy Phase 1, #1–#18 | 21.5 din |
| `07` §13.8 | Custom domains (Phase 2) | 15.0 din |
| `08` §5 | sso-client + example integration | 13.5 din |
| `09` | Deployment guide | **koi estimate nahi** |
| `10` §11 | Pehla product (total) | 52.5 din = 13.5 + 39 |

### 0.2 Sum kabhi nahi — overlap ledger lazmi

**Yeh roadmap ka sab se ahem rule hai.** Docs alag alag likhe gaye, is liye unke numbers **ek doosre ko overlap** karte hain. Seedha jama karna 30–40 din ka jhoot banata hai:

| Overlap | Kya | Kitna |
|---|---|---|
| `04` **S6** (OAuth) vs `05` **I1–I4** | Dono Passport clients + `/oauth/userinfo` + `resolve` + `renew-link` banate hain | −5.0 din |
| `04` **S4** (Billing) vs `06` **P1.2–P1.4** | Dono `invoices`/`payments` + TRX queue + invoice PDF | −6.0 din |
| `07` **#2** (folder split) vs **D2** | `07` §12.1 ka #2 wohi split hai jo `03` D2 kehta hai | −2.5 din |
| `07` **package work** (v2.0.0) vs **D3** | `07` §12.1 kehta hai "~3.5 din ismein shamil hain" | −3.5 din |
| `08` **13.5** vs `10` **52.5** | `10` §11 kehta hai: "dono numbers overlap karte hain, jama nahi kiye" | andar hai |

**Jahan overlap nahi hai:** `07` (tenancy) aur `08` (sso-client) — `08` §5 khud likhta hai "yeh effort `07` ke effort se overlap nahi karta", sirf `TenantTestCase` shared hai jo `07` mein gina gaya.

### 0.3 "Done when" = command, opinion nahi

Har phase ka ek **testable exit** hai. "Kaam ho gaya lagta hai" valid nahi. Jahan doc ne test IDs diye hain (`05` T1–T56, `06` T1–T56, `07` T1–T38, `08` T1–T25), wohi IDs yahan hain.

---

## 1. Aaj kahan hain — 14 Nov 2026

Yeh section **claims** nahi, repo ki asli haalat hai:

| Cheez | Haalat | Evidence |
|---|---|---|
| Laravel / PHP | 13.38.0 / 8.5.12, Composer 2.10.3 | `composer.json` |
| Packages extracted | `khattakbits/core` v1.x, `@khattakbits/ui` v1.x | `../khattakbits-core`, `../khattakbits-ui` |
| Composer repo type | **`path`** — CI ka asli masla | `composer.json` `repositories` |
| Git tags | Sirf **`pre-extraction`** — `pre-tenancy` **maujood nahi** | `git tag -l` |
| Migrations | 12 (core) + 3 (host), **koi `central/`/`tenant/` split nahi** | `03` D2 pending |
| CP tables | **Zero** — `04` §1.3 ke 18 tables design hain, bane nahi | `04` |
| `laravel/passport` | **Install nahi** — pin `13.10.*` | `05` §0.2.1 |
| `stancl/tenancy` | Resolve-verified v3.10.1, **install nahi** | `07` §1 |
| `khattakbits/sso-client` | **Maujood nahi** — poora package `08` mein design hai | `08` §2 |
| `make:module` | v1 maujood hai, tenancy-aware **nahi** | `10` §6.1 |
| `khattakbits:manifest` | **Maujood nahi** | `04` §6.1, `10` §4.3 |
| Tests | 101 passed / 543 assertions (12 Nov) | `docs/README.md` |
| Docs | `02`–`10` complete, `09` authoritative over old `docs/02-DEPLOYMENT-GUIDE.md` | `docs/platform/README.md` |

**Yani:** design 100% done hai, implementation 0% — magar **extraction** ho chuki hai (packages alag repos mein, namespaces `KhattakBits\`). Yeh achhi khabar hai: Phase 0 ka sab se bara kaam ho chuka hai.

---

## 2. Dependency graph

```text
                        ┌──────────────────────────────┐
                        │ P0  Baseline + D2 + D3 + D4  │  6.0d
                        │  tag pre-tenancy, core v2.0.0│
                        └──────────────┬───────────────┘
                                       │  (migration split + routes split)
              ┌────────────────────────┼────────────────────────┐
              ▼                        ▼                        ▼
   ┌────────────────────┐   ┌────────────────────┐   ┌────────────────────┐
   │ P1  sso-client     │   │ P2  CP build (04)  │   │ P5  Tenancy (07)   │
   │     skeleton       │   │  40d               │   │  15.5d             │
   │     1.0d           │   └─────────┬──────────┘   └─────────┬──────────┘
   └─────────┬──────────┘             │                        │
             │              ┌─────────┴──────────┐             │
             │              ▼                    ▼             │
             │   ┌────────────────────┐  ┌──────────────┐      │
             │   │ P3  Identity (05)  │  │ P4 Payments  │      │
             │   │     27.5d          │  │  P1  25d     │      │
             │   └─────────┬──────────┘  └──────┬───────┘      │
             │             │                    │              │
             └─────────────┴────────┬───────────┘              │
                                    ▼                          │
                        ┌────────────────────────┐             │
                        │ P6  Deployment (09)    │◄────────────┘
                        │     NOT ESTIMATED      │
                        └───────────┬────────────┘
                                    ▼
                        ┌────────────────────────┐
                        │ P7  sso-client wiring  │  12.5d
                        │  + pehla product       │  + 39d  = 51.5d
                        └───────────┬────────────┘
                                    │
                    ┌───────────────┴────────────────┐
                    ▼                                ▼
        ┌──────────────────────┐        ┌──────────────────────┐
        │ P8 Payments Phase 2  │        │ P9 Custom domains    │
        │   20d effort         │        │   15d                │
        │   6–8 hafte calendar │        │                      │
        └──────────────────────┘        └──────────────────────┘
```

**Kyun `P1` (skeleton) `P2` (CP) se pehle:** skeleton CP ke **baghair** likha ja sakta hai — `config/kb.php`, 5 migrations, models, aur `Http::fake` par tests (`08` §5, "CP ke baghair test ho sakti hai, magar end-to-end nahi"). Iska faida: CP ka API contract ek **asli consumer** ke saath likha jata hai, na ke baad mein retrofit hota hai. `08` §5 ka #1 (1.0d) bilkul yahi hai.

**Kyun `P5` (tenancy) `P3`/`P4` ke saath parallel chal sakta hai:** tenancy product-side kaam hai (`stancl/tenancy`, route restructure, `tenantUrl()`), CP-side kaam se sirf `GET /api/v1/users/tenants` par milta hai (`05` I8). Baaki independent hai.

---

## 3. Phase map — ek nazar

| Phase | Kaam | Effort | Doc source | Blocked by | Parallel with |
|---|---|---|---|---|---|
| **P0** | Baseline tag + D2 split + D3 core v2.0.0 + D4 `UPGRADE.md` | **6.0d** | `07` §12.1 #2 (2.5) + package line (3.5) | — | — |
| **P1** | `sso-client` skeleton (composer, provider, config, 5 migrations, models) | **1.0d** | `08` §5 #1 | P0 | — |
| **P2** | CP: catalog, plans, subscriptions, billing, storefront, webhooks, access | **40.0d** | `04` §7 S0–S3, S5, S7–S9 (51 − 5 − 6) | P0 | P5 |
| **P3** | Identity hub: Passport, OIDC, resolve, webhooks, disable propagation | **27.5d** | `05` §8 I0–I9 | P2 (S1, S3, S7 tables) | P5 |
| **P4** | Payments Phase 1 (manual TRX) | **25.0d** | `06` §6.1 P1.0–P1.6 | P2 (S3) | P3 ke baad |
| **P5** | Tenancy in product (path-based) | **15.5d** | `07` §12.1 (21.5 − 2.5 − 3.5) | P0 | P2 |
| **P6** | Deployment (Ubuntu 24.04, MySQL, Nginx, backups) | **NOT ESTIMATED** | `09` (koi number nahi) | P3, P4, P5 | — |
| **P7** | `sso-client` wiring + pehla product | **51.5d** | `08` §5 (13.5 − 1.0 = 12.5) + `10` §11 (39) | P1, P3, P5, P6 | — |
| **P8** | Payments Phase 2 (gateway APIs) | **20.0d effort** | `06` §6.2 P2.0–P2.7 | P4, gateway onboarding | P9 |
| **P9** | Custom domains | **15.0d** | `07` §13.8 | P5, P7 | P8 |

**Phase 1 = P0 → P7 = 166.5 din.** Hisaab §5 mein.

---

## 4. Phases — tafseel

### 4.1 P0 — Baseline, D2 split, `core` v2.0.0 — 6.0 din

**Kyun sab se pehle:** teenon cheezein **package ka public contract** torti hain. Inhein baad mein karna = har product ko dobara migrate karna. `03` Part D ka apna rule: "package release se **pehle**, kyunke woh package ka public contract tor rahe hain."

| # | Deliverable | Source |
|---|---|---|
| 1 | `git tag pre-tenancy` (aaj sirf `pre-extraction` hai) | `03` Part D |
| 2 | `database/migrations/{central,tenant}/` — **4 central, 11 tenant** | `03` D2 |
| 3 | `CoreServiceProvider` mein do `loadMigrationsFrom()` + `ModuleRegistry::migrationPaths()` | `03` D2.1, `07` §7.3 |
| 4 | `core` routes ka split — `attachments` + `document-activity` `{tenant}` ke andar | `03` D3.1, `07` §3.4 |
| 5 | `menu.php` contract mein `path` key (static) + `href` (runtime) | `03` D3.2, `07` §5.3 |
| 6 | `khattakbits/core` **v2.0.0** tag + `UPGRADE.md` (codemod C1–C6) | `03` D3, D4 |
| 7 | `MigrationSplitTest` (permanent) | `03` D2, `10` §2.5 |

**Effort:** `07` §12.1 #2 = **2.5d** (isme Step 0 cross-DB FK shamil hai jo `03` D1 mein **ho chuka** — is liye asli bacha hua kaam is se kam hai, magar doc ne alag number nahi diya) + package work **3.5d** (`07` §12.1, "~3.5 din ismein shamil hain").

**Done when:**

```bash
# 1. Split sahi hai — tenant DB mein users NAHI, attachments HAI
php artisan test --filter=MigrationSplitTest

# 2. Cross-DB FK zero (D1 ka guard, hamesha)
grep -rn "constrained('users')\|references('users')" \
  database/migrations vendor/khattakbits/core/database/migrations
# → tenant tables mein zero hit

# 3. Tenant migrate sirf 11 chalaye, central sirf 4
php artisan migrate --pretend | grep -c "create table"     # → 4
php artisan tenants:migrate --pretend | grep -c "create table"  # → 11

# 4. Purana behaviour toota nahi
php artisan test && vendor/bin/pint --test

# 5. Tag maujood
git tag -l 'pre-*'   # → pre-extraction, pre-tenancy
```

**Parallel:** kuch nahi — yeh blocking hai.

---

### 4.2 P1 — `sso-client` skeleton — 1.0 din

**Kyun CP se pehle:** `08` §5 ka #1 sirf skeleton hai (composer, provider, config, 5 migrations, models) — ismein koi CP call nahi. Yeh CP ke API contract ko **consumer-first** likhne deta hai.

| # | Deliverable |
|---|---|
| 1 | Repo `khattakbits-sso-client` + `composer.json` (`vcs`, `path` nahi) |
| 2 | `SsoClientServiceProvider` + `config/kb.php` (sirf `env()`) |
| 3 | 5 **central** migrations: `users` columns, `kb_user_tenant`, `kb_snapshot`, `kb_oauth_state`, `webhook_events` |
| 4 | Models: `KbOauthState`, `KbSnapshot`, `KbUserTenant`, `WebhookEvent` |
| 5 | `loadMigrationsFrom(__DIR__.'/../database/migrations/central')` |

**Done when:**

```bash
# Package install hota hai aur migrations central par chalti hain
composer require "khattakbits/sso-client:^1.0" --with-dependencies
php artisan vendor:publish --tag=kb-config
php artisan migrate
# → 5 tables: kb_user_tenant, kb_snapshot, kb_oauth_state, webhook_events (+ users columns)
```

**Trap:** paanchon migration **central** mein — `08` §5.4. Tenant DB mein daalne se webhook dedupe kaam hi nahi karta (event kis tenant ka hai, woh payload se aata hai, URL se nahi).

---

### 4.3 P2 — Control plane (`04`) — 40.0 din

**Scope:** `04` §7 ke S0–S3, S5, S7, S8, S9. **S4 aur S6 ismein nahi** — woh `06` aur `05` mein replace ho gaye (§0.2).

| Sprint | Deliverable | Din |
|---|---|---|
| **S0** | MySQL confirm + `security_events` (core) + Passport install/config + `khattakbits:manifest` skeleton | 2 |
| **S1** | Catalog: `products`, `product_modules`, `product_features`, `product_doctypes`, `product_reports` + admin screens 1–7 + manifest sync (`04` §6) | 6 |
| **S2** | Plans: `plans`, `plan_prices`, `plan_entitlements`, resolver + hash + cache (`04` §3), `EntitlementSet` in core, screens 8–10 | 6 |
| **S3** | Tenancy + subscription: `tenants_registry`, `subscriptions`, `subscription_events`, state machine, scheduler, provisioning client, screens 11–13, 19–20 | 7 |
| **S5** | Storefront: Blade layout + 8 pages + SEO + checkout + transactional emails | 6 |
| **S7** | Webhooks: `webhook_endpoints`, `webhook_deliveries`, HMAC, retries/backoff, dead-letter, screens 23–24 | 4 |
| **S8** | Access + polish: `kb_user_product_access`, screens 25–28, KPI dashboard, `platform:rehash` | 4 |
| **S9** | Hardening: rate limits, audit coverage, feature tests, seeders (demo product + 3 plans), docs | 5 |
| | **Total** | **40** |

**S4 (6d) kahan gaya:** `06` P1.2–P1.4 wohi kaam karta hai (checkout, TRX queue, verification, invoice PDF) — magar **zyada tafseel se** (resubmit/unlock flows, SLA badges, 4 email templates). Dono karna duplicate hai.

**S6 (5d) kahan gaya:** `05` I1–I4 wohi kaam karta hai (Passport clients, redirect allowlist, PKCE, `/oauth/userinfo`, `resolve` ETag/304, `renew-link`) — 12.5 din mein, kyunke `05` ne poora OIDC layer + login interrupt + users-changes feed add kiya.

**Done when:**

```bash
# 18 tables + 28 admin screens
php artisan migrate --pretend | grep -c "create table"        # → 18 (04 §1.3)
php artisan test --filter=ManifestImportTest                   # V1–V4 green (10 §4.6.3)
php artisan platform:rehash --dry-run                          # idempotent: second run 0 changes
php artisan db:seed --class=DemoProductSeeder                  # 1 product, 4 modules, 3 plans
php artisan test --filter=GetRoutesAreReadOnlyTest             # 05 §7.1 gate
```

| Test | Kya prove karta hai |
|---|---|
| `ManifestImportTest` | Module/feature absent → `status=removed`, **delete nahi** (`04` §6.2) |
| `EntitlementHashTest` | Canonical JSON + `sha256` stable — do runs, same hash (`04` §3.3) |
| `SubscriptionStateMachineTest` | `pending → active → past_due → expired` — illegal transitions `422` |
| `GetRoutesAreReadOnlyTest` | Koi GET route data nahi likhta (`05` §7.1) |

**Parallel:** P5 (tenancy) — sirf `GET /api/v1/users/tenants` par milta hai jo P3 mein hai.

---

### 4.4 P3 — Identity hub (`05`) — 27.5 din

| Sprint | Deliverable | Din |
|---|---|---|
| **I0** | `ext-sodium` + CI extensions + `laravel/passport:13.10.*` + core migration (`blocked_at`, `blocked_reason`, `token_version`) + **tag `v1.5.0`** + guards + keys | 2 |
| **I1** | Passport wiring: clients auto-create on product create, redirect allowlist, scope ceiling, PKCE, secret rotation | 4 |
| **I2** | OIDC: `oauth_signing_keys`, RS256, JWKS, discovery, userinfo, key rotation | 3 |
| **I3** | Login interrupt: `oauth_auth_requests`, `/oauth/authorize`, session re-bind, 5 `isSafeRedirect` fixes, host allowlist | 3 |
| **I4** | API: resolve, entitlements, users/changes feed, renew-link, introspect, revoke + rate limits | 4.5 |
| **I5** | Webhooks: envelope, HMAC, retry/backoff, delivery log, replay, admin replay | 3 |
| **I6** | Disable propagation: `user_security_changes` fan-out, `EnsureTokenFresh`, session purge | 3 |
| **I7** | Tests T1–T56 + docs | 3 |
| **I8** | `GET /api/v1/users/tenants` — query, `product_key` filter, ETag, fail-closed | 1 |
| **I9** | `GET /api/v1/plans` + `tenant.provisioned` retirement + `blocked_reason` payloads + CI gate | 1 |
| | **Total** | **27.5** |

**Dependencies (`05` §8):** I1 → S1 tables. I4 → S3 `subscriptions`. I5 → S7 `webhook_endpoints`. I0 **blocking** hai aur usmein **package release** shamil hai.

**Done when:**

```bash
php -m | grep sodium                        # → sodium (warna Passport install hi nahi hota)
composer show laravel/passport | grep versions   # → 13.10.x, 13.11 nahi
php artisan passport:keys
curl -s https://app.khattakbits.com/.well-known/openid-configuration | jq .issuer
curl -s https://app.khattakbits.com/oauth/jwks | jq '.keys | length'   # → >= 1
php artisan test --filter=IdentityTest      # T1–T56
```

| Test | Kya prove karta hai |
|---|---|
| **T21** | Callback URL par crafted `?return_to=` **ignore** hota hai (`08` §2.8.6) |
| **T22** | Browser A ka `state` browser B mein replay → reject (login CSRF) |
| **T23** | Normal flow `session()->regenerate()` ke paar zinda rehta hai |
| **T24/T25** | Envelope `id`/`type` `X-KB-Event-Id`/`X-KB-Event` se `hash_equals` match |
| **T48–T56** | `plans` endpoint: `include_hidden` first-party only, `-1` = unlimited, ETag/304 |

---

### 4.5 P4 — Payments Phase 1 (`06`) — 25.0 din

| Sprint | Deliverable | Din |
|---|---|---|
| **P1.0** | Foundation: config, 4 enums, DTOs, `PaymentGateway` interface, `GatewayRegistry`, `payment_gateway_accounts` (`encrypted:array`), accounts screen, `payments:reencrypt-secrets` | 4.5 |
| **P1.1** | Core `v1.6.0`: `attachments.types[].store` + `?inline=1` safe-mime + `config/attachments.php` `payment` entry | 2 |
| **P1.2** | Checkout: pay screen, TRX form, `submitTrx()` + F1–F15, `amount_expected_minor` freeze, status page + polling + SLA | 4.5 |
| **P1.3** | Verification queue: `/platform/payments/queue`, detail screen, approve/reject + `afterCommit`, reject reason enum, resubmit + unlock, audit | 5.5 |
| **P1.4** | Invoice: PDF + dompdf, signed 7-din link, `moneyMinor()`, 4 email templates | 3 |
| **P1.5** | `subscriptions:remind` + `payments:sla-alert` + `admin_notify_mode` + scheduler (`withoutOverlapping`/`onOneServer`) | 2 |
| **P1.6** | Tests T1–T40, T50–T56 + docs | 3.5 |
| | **Total** | **25.0** |

**Done when:**

```bash
php artisan test --filter=PaymentsTest        # T1–T40 + T50–T56
php artisan subscriptions:remind --dry-run    # idempotent: dobara 0
php artisan payments:sla-alert --dry-run
php artisan schedule:list | grep -E "remind|sla-alert"
# Manual E2E: checkout → TRX submit → admin approve → subscription active → invoice PDF
```

| Test | Kya prove karta hai |
|---|---|
| Amount tolerance | Underpaid/overpaid `> tolerance` → manual review, auto-approve nahi |
| `unique(gateway, reference_norm)` | Wohi TRX dobara submit → `422`, do payments nahi |
| Resubmit flow | Reject ke baad **naya** `payments` row, purana `rejected` terminal |
| `encrypted:array` | `payment_gateway_accounts.credentials` DB mein plaintext nahi |

**Parallel:** P1.4 (invoice) P1.0 ke baad independent hai.

---

### 4.6 P5 — Tenancy in product (`07`) — 15.5 din

**`07` §12.1 ke 18 items, minus #2 (D2, P0 mein) aur package work (D3, P0 mein).**

| # | Kaam | Din |
|---|---|---|
| 1 | Package install, `config/tenancy.php`, 4 overrides (O1–O4), `SlugPathTenantResolver` | 1.5 |
| 3 | Path identification: route restructure, middleware order, `EnsureTenantMembership`, `onFail` | 2.0 |
| 4 | Reserved slug list + coverage test + slug validation + create flow | 1.0 |
| 5 | `tenantUrl()` (PHP + shared prop + JS) + codemod C1–C6 + CI gate | 2.0 |
| 6 | Provisioning job + provisioner + `tenants:provision`/`destroy` + rollback | 2.0 |
| 7 | `ModuleRegistry::migrationPaths()` + provider change + module layout | 1.0 |
| 8 | Per-tenant: cache prefix, storage, mailer, scheduler, queue pinning | 1.5 |
| 9 | Permissions: `Role`/`Permission` connection + `PermissionRegistrar` cache key | 0.5 |
| 10 | Ops: backup script, export, suspend, DB privileges, deploy runbook | 1.5 |
| 11 | Tests T1–T20 + `TenantTestCase` | 2.0 |
| 12 | Documentation + `02`/`04`/`05` corrections | 1.0 |
| 13–17 | Owner mapping, `failed_at_state` resume, sweeper, DB privileges, membership reconcile | 2.5 |
| 18 | Tests T21–T38 | 1.0 |
| | **Doc total (`07` §12.1)** | **21.5** |

**P5 ka hisaab:** `07` ka doc total **21.5** − #2 (2.5, P0 mein D2) − package work (3.5, P0 mein D3) = **15.5**.

> **Do numbers jo match nahi karte:** upar ki rows jama karne par **19.5** bante hain, magar `07` §12.1 ka total **21.5** likha hai (item-wise 22 bhi banta hai). Farq rounding ka hai — `07` ke rows 0.5 ke round numbers hain. **Hum doc ke `Total` ko authority mante hain, item-wise sum ko nahi**, aur usi se ledger chalate hain (§5). Is liye P5 = 15.5, na ke 19.5 − 6.0 = 13.5.

**Done when:**

```bash
php artisan test --filter=TenancyTest          # T1–T38
php artisan tenants:sweep-stuck --dry-run      # 5 min purana `migrating` → report
php artisan tenants:provision acme --dry-run   # 9 states, resume ke saath
php artisan route:list --path=acme             # {tenant} pehla parameter
```

| Test | Kya prove karta hai |
|---|---|
| **T2** | Tenant A ka session + tenant B ka URL → `404` (URL tampering) |
| **T7** | Tenant A ke permissions tenant B ko **serve nahi** hote (privilege escalation) |
| **T14** | Har tenant route ka `parameterNames()[0] === 'tenant'` |
| **T33** | Membership URL se derive nahi hoti |

---

### 4.7 P6 — Deployment (`09`) — `NOT ESTIMATED`

**`09` mein koi effort number nahi hai** — main guess nahi karunga. Yeh phase **P3/P4/P5 ke baad** hai kyunke deploy karne ke liye kuch hona chahiye.

| Deliverable | Source |
|---|---|
| Ubuntu 24.04 prep: deploy user, SSH key-only, UFW, fail2ban, unattended-upgrades, swap, Asia/Karachi | `09` §1 |
| PHP 8.5 + extensions (**`sodium` lazmi**), Composer, Node, MySQL 8.0, Redis, Supervisor | `09` §2 |
| 3 credentials per product (`_app`, `_provision`, `_backup`) + exact grants | `09` §3.3, `07` §10.2 |
| Nginx: ek block per product domain, plain certbot HTTP-01 — **koi wildcard nahi** | `09` §4–§5 |
| Private packages: `vcs` + deploy key + `auth.json` (CI ka asli fix) | `09` §6 |
| `.env` templates (CP + product), `SESSION_DOMAIN` khali | `09` §7 |
| Supervisor: 4 CP queues (`default`, `webhooks`, `provisioning`, `mail`) | `09` §9 |
| `tenants:sweep-stuck` har 5 min, subscription tick **sirf CP par** | `09` §10 |
| Backup: central + **saare** tenant DBs, `APP_KEY` offline copy | `09` §12 |

**Done when:**

```bash
php -m | grep sodium
composer check-platform-reqs
curl -sI https://app.khattakbits.com/up | head -1        # → 200
curl -sI https://mizaanbitserp.com/acme/dashboard | head -1
supervisorctl status | grep -v RUNNING                    # → khali
php artisan schedule:list | grep sweep-stuck              # → every 5 min
/usr/local/bin/kb-backup --dry-run                        # central + N tenant DBs
```

**`NOT VERIFIED` (3 items — `09` §0.5):** `GRANT CREATE ON \`prefix\_t\_%\`.*` probe, certbot deploy-hook reload, `tenants:migrate` rollback mid-pipeline.

---

### 4.8 P7 — `sso-client` wiring + pehla product — 51.5 din

**Do hisse, do sources:**

| Hissa | Kaam | Din | Source |
|---|---|---|---|
| **7a** | `sso-client` wiring: OAuth/PKCE, mirror, resolve ladder, guard, entitlements, webhook, billing pages, tests T1–T25 | 12.5 | `08` §5 (13.5 − 1.0 skeleton) |
| **7b** | Product modules, reports, plan gating, UAT, go-live | 39.0 | `10` §11 (`04-DEVELOPMENT-GUIDE` §10 ka 50 − 11) |
| | **Total** | **51.5** | |

**Product choice:** **MizaanbitsERP** ya **LawBits** — yeh **khula faisla hai** (§7, decision #8). `08` poora MizaanbitsERP par likha hai (`key=mizaan`, 4 modules, 15 features, 6 doctypes, 3 plans), is liye Mizaan chunna **0 din** ka kaam hai; LawBits chunna `08` ko re-key karna hai (~1–2 din, `08` §1 ka seeder + `10` §4 ka manifest).

**Done when** — `10` §9 ki poori checklist, aur usmein se yeh 6 sab se ahem:

```bash
php artisan khattakbits:manifest --check            # CI green (10 §4.3)
php artisan test --filter=GetRoutesAreReadOnlyTest  # nightly gate (10 §8)
grep -rn "v-html" resources/js/ | wc -l             # → 0 (07 §4.2 M3)
grep -rn "client_secret" resources/js/ | wc -l      # → 0
php artisan test                                    # T1–T25 (08) + module tests (10 §6.6)
curl -sI https://mizaanbitserp.com/acme/dashboard | head -1   # → 200, real tenant
```

**Critical path:** `make:module` v2 (`10` §6) → pehla module → manifest sync (`10` §4) → CP registration → SSO (`08` §2) → entitlement gate → UAT.

---

### 4.9 P8 — Payments Phase 2 (`06` §6.2) — 20 din effort

**Yeh calendar ka jhoota phase hai:** effort 20 din, magar **calendar 6–8 hafte** — merchant onboarding ka intezaar (`06` §6.2).

| Sprint | Kaam | Din |
|---|---|---|
| P2.0 | Onboarding: credentials, sandbox, **V1–V20 verify**, `gateway_callbacks`, callback route | 3 |
| P2.1 | JazzCash driver (`createIntent`, signature, callback, query) — V1–V6 par depend | 3 |
| P2.2 | EasyPaisa driver — V7–V10 | 2.5 |
| P2.3 | Card driver (hosted redirect + 3DS) — V11–V14 | 3 |
| P2.4 | Callback handler: idempotency `payload_hash`, replay window, IP allowlist, redaction | 2 |
| P2.5 | Reconciliation: `payments:reconcile`, 3 mismatch classes, auto-heal | 2 |
| P2.6 | Refunds: `payment_refunds`, admin UI, driver `refund()`, invoice recompute | 2.5 |
| P2.7 | Tests T41–T49 + sandbox E2E | 2 |
| | **Total** | **20** |

**Shuru kab karein:** **P4 ke foran baad** — nahi kyunke kaam ready hai, balke kyunke onboarding ki **waiting** sab se lambi hai. Paperwork P4 ke saath parallel chalna chahiye.

**Done when:** sandbox E2E — intent → callback → `active`, aur `payments:reconcile` zero unexplained mismatch.

---

### 4.10 P9 — Custom domains (`07` §13.8) — 15 din

| # | Kaam | Din |
|---|---|---|
| 1 | `tenant_domains` table + model + repository | 1.0 |
| 2 | DNS TXT verification job + re-verification cron + blocklist | 1.5 |
| 3 | `InitializeTenancyByHostOrPath` + host resolver + route group duplication + name collision fix | 2.0 |
| 4 | `tenantUrl()` extension (`prefer`, `signed()`, `callback()`) + `forceRootUrl` | 1.5 |
| 5 | `sso_handoffs` wapas (sirf custom domain ke liye) | 1.5 |
| 6 | Admin screens (list, add, verify, primary, remove) + permissions + audit | 2.0 |
| 7 | TLS: Caddy on-demand + `/internal/tls-ask` + Nginx restructure + deployment guide rewrite | 2.5 |
| 8 | Entitlement wiring + CP webhooks + `tenants_registry` mirror | 1.0 |
| 9 | Tests: host resolution, takeover, re-verification, TLS ask, SSO on custom domain | 2.0 |
| | **Total** | **15.0** |

**Yeh Phase 2 hai — `07` §13.7 ka entitlement gate hai, is liye yeh "premium" feature ban sakta hai.** Shuru karne se pehle: kya koi customer ne **manga** hai? Nahi to yeh 15 din P8 se zyada value nahi deta.

---

## 5. Effort ledger — poora hisaab

### 5.1 Doc totals (jaisa likha hai)

| Doc | Number |
|---|---|
| `04` §7 CP (S0–S9) | 51.0 |
| `05` §8 Identity (I0–I9) | 27.5 |
| `06` §6.1 Payments P1 | 25.0 |
| `07` §12.1 Tenancy | 21.5 |
| `08` §5 sso-client | 13.5 |
| **Naive sum** | **138.5** |

**Yeh sum ghalat hai** — `08` §5 khud kehta hai "yeh `07` ke effort se overlap nahi karta", magar `04` ka S6 `05` se overlap karta hai, `04` ka S4 `06` se, aur `07` ka #2/package `03` D2/D3 se.

### 5.2 Overlap ledger

| # | Kaun | Kya replace hua | Delta |
|---|---|---|---|
| 1 | `04` S6 (5d) | `05` I1–I4 (12.5d) | **−5.0** |
| 2 | `04` S4 (6d) | `06` P1.2–P1.4 (13d) | **−6.0** |
| 3 | `07` #2 (2.5d) | `03` D2 → P0 | **−2.5** |
| 4 | `07` package (3.5d) | `03` D3 → P0 | **−3.5** |
| | | **Total overlap** | **−17.0** |

**Chhota double-count jo accept kiya:** `04` S0 mein "Passport install/config" hai aur `05` I0 mein bhi — ~0.5d. I0 uske badle core migration + `v1.5.0` tag bhi karta hai, is liye adjust nahi kiya. **Yeh jaan boojh kar hai, bhool nahi.**

### 5.3 Net — Phase 1

| Phase | Din |
|---|---|
| P0 Baseline + D2 + D3 + D4 | 6.0 |
| P1 sso-client skeleton | 1.0 |
| P2 CP (51 − 5 − 6) | 40.0 |
| P3 Identity | 27.5 |
| P4 Payments P1 | 25.0 |
| P5 Tenancy (21.5 − 2.5 − 3.5) | 15.5 |
| P6 Deployment | **NOT ESTIMATED** |
| P7 Wiring + pehla product (12.5 + 39) | 51.5 |
| **Phase 1 total** | **166.5 din** |

**Cross-check:** `138.5 − 17.0 + 6.0 (P0) + 39.0 (product modules, jo `04/05/06/07/08` mein nahi hain) = 166.5.** ✅

**Phase 2:** P8 = 20.0 din effort, P9 = 15.0 din. Dono Phase 1 ke baad.

### 5.4 Calendar — doc ratios se, guess se nahi

| Setup | Din | Haftay | Mahine |
|---|---|---|---|
| **Solo** (5 working days/week) | 166.5 | **33.3** | ~7.7 |
| **Solo + 20% buffer** | — | **40** | ~9.2 |
| **2 devs** (`04`/`05`/`06` ka ~0.56 ratio) | 93.2 | **18.6** | ~4.3 |

**Ratio kahan se aaya:** `04` §7 — "51 din ≈ 10 hafte solo; 2 devs par ~5.5 hafte" (0.55). `05` §8 — "27.5 din ≈ 5.5–6 hafte solo; 2 devs par ~3 hafte" (0.55). `06` §6.1 — "25 din ≈ 5.5 hafte solo; 2 devs par ~3 hafte" (0.60). Mean ≈ 0.56.

**Yeh 33 hafte kyun lagta hai jab `10` §11 ne "12.5 weeks" kaha:** `10` §11 sirf **pehla product** measure karta hai (52.5 din), **CP + identity + payments** nahi. CP akela 92.5 din hai (`40 + 27.5 + 25`). Yeh farq **scope** ka hai, estimate ki ghalti ka nahi.

**Agar sirf MVP chahiye** (ek product, ek tenant, manual TRX, SSO): P0 6 + P2 ka S1–S3 (19) + P3 ka I0–I4 (16.5) + P4 ka P1.0–P1.4 (19.5) + P5 15.5 + P7 51.5 = **128 din ≈ 26 hafte solo**. Phir bhi 6 mahine — kyunke CP ke bina koi product nahi.

---

## 6. Risk register

| ID | Risk | Phase | Asar | Mitigation | Source |
|---|---|---|---|---|---|
| **RK1** | **XSS ka blast radius** — path-based mein ek origin, is liye ek XSS **poore product** ka | P5, P7 | Critical | CSP `enforce` (report-only nahi) + zero `v-html` | `07` R1 |
| **RK2** | URL tampering se cross-tenant access (cookie jati hai) | P5 | High | `EnsureTenantMembership` + **T2** | `07` R2 |
| **RK3** | `PermissionRegistrar` ka global cache key → tenant A ke permissions tenant B ko | P5 | **Critical** (privilege escalation) | Per-tenant cache key + **T7** | `07` R7, §2.3.1a |
| **RK4** | `Cache::tags()` — `CACHE_STORE=file` taggable nahi → **tests pass, prod 500** | P5 | High | `CacheTenancyBootstrapper` off + manual `t:{id}:` prefix | `07` R6 |
| **RK5** | `GRANT CREATE ON prefix\_t\_%.*` — **NOT VERIFIED** | P6 | High | `09` §3.3 probe; fail par Option A ke 4 controls | `07` R18, `09` §0.5 |
| **RK6** | `composer.json` mein `type: path` → CI mein **toota symlink**, install "successful" | P0, P7 | High | `vcs` + deploy key + `auth.json`; `ls -la vendor/khattakbits/` | `09` §6 |
| **RK7** | Passport `^13.10` → `13.11.x` → vulnerable `phpseclib` **exact pin** | P3 | High | `13.10.*` exact pin + `composer check-platform-reqs` | `05` §0.2.1 |
| **RK8** | Gateway onboarding **6–8 hafte** calendar (J1–J12, E1–E11, C1–C12) | P8 | High (schedule) | Paperwork P4 ke saath shuru, P8 ka intezaar nahi | `06` §6.2 |
| **RK9** | Tax/legal jawab (A1–A5) na aane par invoice format galat → **penalty** | P4 | High | Sab config se drive; A1–A5 **abhi** bhejein | `06` §0.6 |
| **RK10** | Owner mapping missing → tenant `ready` magar koi owner nahi, picker khaali | P5 | High | `owner_mapping` state + CP payload | `07` R16 |
| **RK11** | Stuck provisioning — worker kill par row hamesha `migrating` | P5, P6 | Medium | `tenants:sweep-stuck` har 5 min + `failed_at_state` | `07` R17 |
| **RK12** | `read_only` bypass — naya module `GET` route jo likhta hai | P7 | Medium | `GetRoutesAreReadOnlyTest` nightly (`05` §7.1) | `05` §7.1, `10` §8 |
| **RK13** | Membership URL se ban jaye → tenant hopping | P5 | High | `GET /api/v1/users/tenants` + **T33** | `07` R19 |
| **RK14** | CP `subscription.created` mein owner nahi → product side block | P2, P3 | **Blocking dependency** | CP backlog item (~1d, `07` §11.3) | `07` R20 |
| **RK15** | `URL::defaults` Octane leak — tenant A ka base URL tenant B ko | P6 | High | Octane **na chalayein** (`NOT VERIFIED`) | `07` R15 |
| **RK16** | `sessions` table tenant DB mein **hoti hi nahi** → `session.connection` null chhorna = 500 | P5 | High | `session.connection = 'central'` pin | `07` §2.4, `03` D2 |
| **RK17** | Docs drift — manifest haath se likha jaye to CP catalog purana | P7 | Medium | `khattakbits:manifest --check` CI mein | `10` §4.3 |
| **RK18** | Solo dev **bus factor 1** — 166 din ka kaam ek shakhs par | All | High | Docs hi mitigation hain; `02`–`11` isi liye tafseel se likhe | — |
| **RK19** | Advisory backlog — `composer audit` 7 advisories (Passport install ke baad bhi 7) | P3 | Low–Medium | `symfony/*` patch bump — alag kaam | `05` §0.5 |
| **RK20** | Payment proofs retention 1 saal vs `storage/app/private/attachments` backup | P4, P6 | Medium | `09` §12 backup script mein 365 din | `06` §5.4 |

---

## 7. Decisions still open

**Yeh woh cheezein hain jo aap ke baghair band nahi hotin.** Har ek ke saath "kis phase se pehle chahiye".

### 7.1 Accountant / tax (A1–A5) — `06` §0.6

| ID | Sawal | Phase | Kyun blocking |
|---|---|---|---|
| **A1** | **Merchant wallet account type aur limits** — personal ya merchant/business? Har type ka receive limit, per-transaction limit, monthly cap? | P4 | Personal wallet ka monthly cap hit hone par payments **silently fail** hongi — customer bhejega, account reject karega, humein pata nahi chalega |
| **A2** | **Sales-tax invoice format** — NTN, STRN, tax rate, tax amount alag line, buyer NTN, date format, specific wording? | P4 | PDF template isi par banega (`resources/views/invoices/pdf.blade.php`) |
| **A3** | **Invoice numbering + gap rules** — gap-free lazmi? Fiscal year calendar ya July–June? Kitne saal retain? Void ka treatment? | P4 | Gap-free ho to `Naming::next()` ka "number at checkout" kaam nahi karega — draft-then-assign chahiye (+1 din) |
| **A4** | **Sales-tax registration lazmi hai?** Threshold, service export treatment, STRN kaise milega? | P4 | Agar nahi, to A2/A3 **moot** ho jate hain |
| **A5** | **Wallet fee kaun bear karta hai** — sender ya receiver? Amount se alag dikhti hai? | P4 | Receiver kate to har payment "underpaid" dikhega; tolerance = fee karna padega |

**Sab `NOT VERIFIED` hain aur design mein config se drive hote hain** — jawab aane par code change nahi chahiye (`06` §0.6).

### 7.2 Non-accountant open items

| # | Faisla | Phase | Default agar jawab na mile |
|---|---|---|---|
| 6 | **Gateway onboarding lead time** — JazzCash / EasyPaisa / card ka approval kitna waqt lega? (`06` §6.2: **6–8 hafte** calendar) | P8 | P8 ko P4 ke **baad** schedule karein, saath nahi |
| 7 | **MySQL grants probe** — kya `GRANT CREATE ON \`mizaan\_t\_%\`.*` `CREATE DATABASE` deta hai? (`NOT VERIFIED`, `09` §3.3) | P6 | Assume **fail** → `09` Option A (provisioning credential `ON *.*`, 4 mitigating controls) |
| 8 | **Pehla product kaunsa** — MizaanbitsERP ya LawBits? | P7 | Mizaan (0 din — `08` poora usi par hai) |
| 9 | **Wallet account type** — A1 ka core; agar wallet personal hai to **business** par shift karna hoga (paperwork, 2–4 hafte) | P4 | Merchant account assume karein, magar **verify** karein |
| 10 | **Introspection cache store** — `database` ya Redis? | P3 | `database` (`05` §9 — Redis Phase 2 optimisation, blocking nahi) |
| 11 | **`prompt=none` silent SSO** — central host par iframe check? | P3 | Nahi — third-party cookie blocking isay torti hai (`05` §9) |
| 12 | **`auth_time` max age / step-up auth** | P3 | Nahi — claim emit karein, enforce na karein (`05` §9) |

---

## 8. NEXT 10 ACTIONS — is hafte

Order **dependency ke hisaab se** hai, priority ke nahi. #1–#4 aaj ho sakte hain.

| # | Action | Kyun ab | Command / artifact | Waqt |
|---|---|---|---|---|
| **1** | **`git tag pre-tenancy`** — aaj sirf `pre-extraction` hai | D2/D3 package contract torte hain; rollback point abhi chahiye | `git tag -a pre-tenancy -m "Before path-based tenancy (D2/D3)" && git push --tags` | 2 min |
| **2** | **MySQL grants probe** — scratch server par `09` §3.3 ka script | Yeh ek jawab poora deployment model badal deta hai (Option A vs B) | `09` §3.3 probe script; result `07` §10.2.3 aur `09` §0.5 mein update karein | 1 ghanta |
| **3** | **A1–A5 accountant ko bhejein** — ek email, 5 sawal | Invoice format P4 se **pehle** chahiye; jawab aane mein hafte lagte hain | `06` §0.6 ke 5 sawal copy karein; jawab `06` §0.6 ke status column mein | 30 min |
| **4** | **Gateway onboarding paperwork shuru** — JazzCash + EasyPaisa merchant application | 6–8 hafte lead time — P8 se **pehle** shuru karna lazmi | `06` §3.9 ki documents list | 2 ghante |
| **5** | **`composer.json`: `path` → `vcs`** + deploy key + `auth.json` | CI ka asli failure yahi hai; har aage ka phase isi par khara hai | `10` §1.2; verify: `ls -la vendor/khattakbits/` mein symlink **nahi** | 2 ghante |
| **6** | **`php -m` mein `sodium`** — target server + CI extensions | Passport `ext-sodium` ke baghair install hi nahi hota | `05` §0.2.2, `09` §2 | 15 min |
| **7** | **D2 branch** — 4 central / 11 tenant split + `MigrationSplitTest` | P0 ka pehla hissa; P5 iske baghair start nahi ho sakta | `03` D2 inventory; `10` §2.5 ka test | 2.5 din |
| **8** | **D3 `core` v2.0.0** — routes split + `menu.php` `path` + `UPGRADE.md` | P0 ka doosra hissa; `menu.php` contract sab products ko todta hai | `03` D3.1/D3.2/D4 | 3.5 din |
| **9** | **`04` §1.3 ke 18 tables ke migrations** (S1 se shuru) | CP ka pehla sprint; `05`/`06` dono in tables par depend hain | `04` §1.3 + `04` §7 S1 | 2 din |
| **10** | **Pehla product ka faisla** — Mizaan ya LawBits | P7 ka scope isse tay hota hai; `08` Mizaan par likha hai | Faisla + `08` §1 ka seeder re-key (agar LawBits) | 1 ghanta |

**Is hafte ka target:** #1, #2, #3, #4, #6, #10 (sab chhote) + #5 shuru. **#7–#9 agle hafte** — woh P0 ka asli kaam hai.

---

## 9. Changelog

### 14 Nov 2026 — v1 (initial)

| # | Kya | Kyun |
|---|---|---|
| 1 | Naya document — pehli dafa **poore platform** ka dependency-ordered schedule | `02`–`10` design hain; koi doc yeh nahi batata ke **kya pehle** aur **kitna waqt** |
| 2 | **Overlap ledger (§5.2)** — 17 din ka double-count pakda | `04` ka S6 `05` se, S4 `06` se, `07` ka #2/package `03` se overlap karta hai. Seedha sum 138.5 deta hai jo ghalat hai |
| 3 | **P0 alag phase** — tag + D2 + D3 + D4 ek saath | `03` Part D kehta hai yeh "package release se **pehle**" — magar kisi doc ne isay phase nahi banaya |
| 4 | **P1 `sso-client` skeleton CP se pehle** | `08` §5 #1 sirf skeleton hai; consumer-first contract likhne ke liye |
| 5 | **Risk register R1–R20 + 5 naye** (RK6, RK7, RK8, RK9, RK17) | `07` §12.2 ke R1–R20 design risks hain; deployment/CI/passport/advisory risks kisi register mein nahi thay |
| 6 | **Decisions still open** — A1–A5 + 7 non-accountant items, har ek ke saath **default** | "Kya khula hai" ka list pehle bikhra hua tha (`05` §9, `06` §0.6, `07` §10.2.3, `09` §0.5) |
| 7 | **NEXT 10 ACTIONS** — har ek ke saath command aur waqt | Roadmap actionable nahi tha |

**Explicitly `NOT VERIFIED`:**

- **`09` (deployment) ka effort** — `09` mein koi din ka number nahi hai, is liye `NOT ESTIMATED` likha. Guess nahi kiya.
- **2-dev ratio 0.56** — `04`/`05`/`06` ke teen alag ratios (0.55, 0.55, 0.60) ka mean hai, kisi doc ne poore platform ka 2-dev number nahi diya.
- **`07` §12.1 #2 ka 2.5d** — usmein Step 0 (cross-DB FK) shamil hai jo `03` D1 mein **ho chuka**. Asli bacha hua kaam is se kam hai, magar doc ne alag number nahi diya — is liye 2.5 hi rakha.
- **MySQL grants probe** — `NOT VERIFIED` (`09` §0.5), P6 ka blocking item.

---

*Yeh roadmap design + schedule hai, code nahi. Har phase ka "done when" command ya test hai — "kaam ho gaya lagta hai" is document mein valid nahi. Effort numbers `02`–`10` se hain, aur jahan overlap tha wahan §5.2 mein khula hua hai.*
