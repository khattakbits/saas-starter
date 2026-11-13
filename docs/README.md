# KhattakBits — Documentation Index

Review date: **12 Nov 2026** · Repo HEAD `aa21c32` · `php artisan test` → **101 passed (543 assertions)**
Platform design docs: **13–14 Nov 2026** · see [`platform/`](platform/)

## Platform design series (`docs/platform/`)

| # | Document | Kya hai andar |
|---|---|---|
| 02 | [02-ARCHITECTURE-DECISIONS.md](platform/02-ARCHITECTURE-DECISIONS.md) | D1–D5 decisions (users central, roles tenant, etc.), versioning/release rules |
| 03 | [03-EXTRACTION-PLAN.md](platform/03-EXTRACTION-PLAN.md) | `khattakbits/core` + `@khattakbits/ui` package extraction |
| 04 | [04-CONTROL-PLANE-DESIGN.md](platform/04-CONTROL-PLANE-DESIGN.md) | 18 CP tables, subscription state machine, product contract, admin screens, provisioning sequence |
| 05 | [05-IDENTITY-HUB-SPEC.md](platform/05-IDENTITY-HUB-SPEC.md) | OAuth2/OIDC + PKCE, login interrupt, API contract (`resolve`, `plans`, `users/changes`), webhooks, disable propagation, CI gate (**v4**) |
| 06 | [06-PAYMENTS-DESIGN.md](platform/06-PAYMENTS-DESIGN.md) | Manual TRX + gateway abstraction, invoices, dunning, `APP_KEY` blast radius + rotation |
| 07 | [07-MULTI-TENANCY-DESIGN.md](platform/07-MULTI-TENANCY-DESIGN.md) | **Path-based** tenancy (`/{tenant}/...`), connection pinning, per-product DB prefix + grants, provisioning resilience, ops |
| 08 | [08-EXAMPLE-PRODUCT-INTEGRATION.md](platform/08-EXAMPLE-PRODUCT-INTEGRATION.md) | MizaanbitsERP reference integration: registration, `khattakbits/sso-client`, E2E runbook, troubleshooting (**v3**) |
| **09** | **[09-DEPLOYMENT-GUIDE.md](platform/09-DEPLOYMENT-GUIDE.md)** | **AUTHORITATIVE deployment guide** — Ubuntu 24.04, PHP 8.5 + sodium, MySQL per-product credentials, Nginx (path-based, **no wildcard**), certbot HTTP-01, private composer packages, `.env`, Supervisor (4 CP + 2 product queues), scheduler, Deployer, CI/CD, backups (central + all tenants), monitoring, checklist |

## Earlier reports

| # | Document | Kya hai andar |
|---|---|---|
| 1 | [01-STATUS-REPORT.md](01-STATUS-REPORT.md) | Executive summary + completion %, structure overview, 28-row feature status matrix, aap ke claims ki verification, bugs/security/tech-debt, P0/P1/P2 roadmap, target architecture, full SSO/API/webhook contract, Passport vs Sanctum decision |
| 2 | [02-DEPLOYMENT-GUIDE.md](02-DEPLOYMENT-GUIDE.md) | ⚠️ **SUPERSEDED 14 Nov 2026** — path-based tenancy se pehle likhi gayi. **`platform/09` parhein.** Sirf §1 (server prep), §2 (OPcache/FPM) aur §16 (compliance) abhi bhi valid hain |
| 3 | [03-INTEGRATION-GUIDE.md](03-INTEGRATION-GUIDE.md) | MizaanbitsERP ka poora product-side integration: 5 migrations, PKCE OAuth client, callback controller, entitlement helper, 2 middleware, HMAC webhook + idempotency, tenant provisioning, expiry/renewal flow, role mapping, tests |
| 4 | [04-DEVELOPMENT-GUIDE.md](04-DEVELOPMENT-GUIDE.md) | Naya product start karne ke 10 steps, folder/naming conventions, `make:module` command ka poora code, plan gating, tenant testing matrix, starter-kit update strategy (git merge vs composer packages), Definition of Done, 10-week timeline |

## One-line verdict

**Starter kit 80 % ready hai, control plane 0 % — vision ka 75 % build karna hai, 25 % refactor.**

Sab se pehla kaam: `products` / `plans` / `subscriptions` tables + Passport + `/api/v1/subscriptions/resolve`. Bina in ke koi bhi doosra kaam (SSO, product integration, billing) possible nahi.
