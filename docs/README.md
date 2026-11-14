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
| 10 | [10-NEW-PRODUCT-PLAYBOOK.md](platform/10-NEW-PRODUCT-PLAYBOOK.md) | Naya product banane ka runbook — day 0 se DoD tak, `kb-product.json` manifest sync + CP import, `make:module` v2, entitlement gating (3 jagah), `GET`-routes read-only CI gate, anti-patterns, 52.5 din |
| 11 | [11-MASTER-ROADMAP.md](platform/11-MASTER-ROADMAP.md) | **Schedule** — P0–P9 dependency order, effort ledger (overlap hata kar, §5), risk register R1–R20 + 5 naye, khule faisle (A1–A5 + 7), **NEXT 10 ACTIONS** |

Poora index, authoritative-file map aur reading order: [`platform/README.md`](platform/README.md)

## Earlier reports

| # | Document | Kya hai andar |
|---|---|---|
| 1 | [01-STATUS-REPORT.md](01-STATUS-REPORT.md) | Executive summary + completion %, structure overview, 28-row feature status matrix, aap ke claims ki verification, bugs/security/tech-debt, P0/P1/P2 roadmap, target architecture, full SSO/API/webhook contract, Passport vs Sanctum decision |
| 2 | [02-DEPLOYMENT-GUIDE.md](02-DEPLOYMENT-GUIDE.md) | ⚠️ **SUPERSEDED 14 Nov 2026** — path-based tenancy se pehle likhi gayi. **`platform/09` parhein.** Sirf §1 (server prep), §2 (OPcache/FPM) aur §16 (compliance) abhi bhi valid hain |
| 3 | [03-INTEGRATION-GUIDE.md](03-INTEGRATION-GUIDE.md) | ⚠️ **Reconcile nahi hua** — purana product-side integration (subdomain + handoff). `platform/08` uska path-based replacement hai; cite karne se pehle cross-check karein |
| 4 | [04-DEVELOPMENT-GUIDE.md](04-DEVELOPMENT-GUIDE.md) | ⚠️ **Partly superseded** — §2.3 (`make:module`), §6, §7, §9, §10 ab `platform/10` mein. §1 (clone), §3 (naming), §8 (update strategy) abhi bhi valid |

## One-line verdict

**Starter kit 80 % ready hai, control plane 0 % — vision ka 75 % build karna hai, 25 % refactor.**

Sab se pehla kaam: `products` / `plans` / `subscriptions` tables + Passport + `/api/v1/subscriptions/resolve`. Bina in ke koi bhi doosra kaam (SSO, product integration, billing) possible nahi.

**Schedule:** 166.5 din Phase 1 (`platform/11` §5.3) — solo ~33 hafte, 2 devs ~19 hafte. Is hafte ke 10 kaam: [`platform/11` §8](platform/11-MASTER-ROADMAP.md).
