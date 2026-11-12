# KhattakBits — Documentation Index

Review date: **12 Nov 2026** · Repo HEAD `aa21c32` · `php artisan test` → **101 passed (543 assertions)**

| # | Document | Kya hai andar |
|---|---|---|
| 1 | [01-STATUS-REPORT.md](01-STATUS-REPORT.md) | Executive summary + completion %, structure overview, 28-row feature status matrix, aap ke claims ki verification, bugs/security/tech-debt, P0/P1/P2 roadmap, target architecture, full SSO/API/webhook contract, Passport vs Sanctum decision |
| 2 | [02-DEPLOYMENT-GUIDE.md](02-DEPLOYMENT-GUIDE.md) | Ubuntu 24.04 server prep, PHP 8.5 + FPM pools, Postgres/Redis, Nginx multi-domain + wildcard tenants, wildcard SSL (DNS-01), `.env` templates, Supervisor, cron, backups, logrotate, Deployer zero-downtime, rollback, GitHub Actions, production checklist |
| 3 | [03-INTEGRATION-GUIDE.md](03-INTEGRATION-GUIDE.md) | MizaanbitsERP ka poora product-side integration: 5 migrations, PKCE OAuth client, callback controller, entitlement helper, 2 middleware, HMAC webhook + idempotency, tenant provisioning, expiry/renewal flow, role mapping, tests |
| 4 | [04-DEVELOPMENT-GUIDE.md](04-DEVELOPMENT-GUIDE.md) | Naya product start karne ke 10 steps, folder/naming conventions, `make:module` command ka poora code, plan gating, tenant testing matrix, starter-kit update strategy (git merge vs composer packages), Definition of Done, 10-week timeline |

## One-line verdict

**Starter kit 80 % ready hai, control plane 0 % — vision ka 75 % build karna hai, 25 % refactor.**

Sab se pehla kaam: `products` / `plans` / `subscriptions` tables + Passport + `/api/v1/subscriptions/resolve`. Bina in ke koi bhi doosra kaam (SSO, product integration, billing) possible nahi.
