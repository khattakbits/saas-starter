# docs/platform — Index

**Yeh KhattakBits platform design series hai.** `01` se `11` tak — architecture decisions se le kar deployment tak.
**Review date:** 14 Nov 2026 · **Status:** design complete, implementation 0% (`11` §1)

---

## 1. Index

| # | File | Ek line mein |
|---|---|---|
| **01** | [`docs/01-STATUS-REPORT.md`](../01-STATUS-REPORT.md) | Executive summary, 28-row feature matrix, bugs/security/tech-debt (B-1, B-2, B-5), P0/P1/P2 roadmap — **platform folder ke bahar hai** |
| **02** | [02-ARCHITECTURE-DECISIONS.md](02-ARCHITECTURE-DECISIONS.md) | Paanch bunyadi faisle (D1–D5), central vs tenant table split, Step 0 cross-DB FK, semver/versioning rules |
| **03** | [03-EXTRACTION-PLAN.md](03-EXTRACTION-PLAN.md) | Packages kaise nikale gaye (Part A–C) + **Part D** — migration split ka exact inventory (4 central / 11 tenant), D2/D3/D4 |
| **04** | [04-CONTROL-PLANE-DESIGN.md](04-CONTROL-PLANE-DESIGN.md) | CP ke 18 tables, entitlement resolver + hash + cache, 28 admin screens, storefront, manifest import (§6), build order S0–S9 = 51d |
| **05** | [05-IDENTITY-HUB-SPEC.md](05-IDENTITY-HUB-SPEC.md) | OAuth2/OIDC hub — Passport `13.10.*`, `resolve`, users/tenants, plans endpoint (§3.1.2), webhooks, disable propagation, 27.5d |
| **06** | [06-PAYMENTS-DESIGN.md](06-PAYMENTS-DESIGN.md) | Payments Phase 1 (manual TRX) + Phase 2 (gateways), invoice numbering, `APP_KEY` blast radius (§5.2), A1–A5 tax sawal |
| **07** | [07-MULTI-TENANCY-DESIGN.md](07-MULTI-TENANCY-DESIGN.md) | **Path-based** tenancy — `stancl/tenancy` v3.10.1, 4 overrides, connection pinning, provisioning + sweeper, DB privileges, custom domains (Phase 2) |
| **08** | [08-EXAMPLE-PRODUCT-INTEGRATION.md](08-EXAMPLE-PRODUCT-INTEGRATION.md) | MizaanbitsERP par poora integration — 8-step registration, `sso-client` package ka real code, E2E runbook + T1–T25, 13.5d |
| **09** | [09-DEPLOYMENT-GUIDE.md](09-DEPLOYMENT-GUIDE.md) | **Authoritative deployment** — Ubuntu 24.04, MySQL 8.0, per-product DB users, Nginx + plain certbot, Supervisor, backups |
| **10** | [10-NEW-PRODUCT-PLAYBOOK.md](10-NEW-PRODUCT-PLAYBOOK.md) | Naya product banane ka runbook — day 0 se DoD tak, `kb-product.json` sync, `make:module` v2, entitlement gating, GET read-only gate, 52.5d |
| **11** | [11-MASTER-ROADMAP.md](11-MASTER-ROADMAP.md) | **Schedule** — P0–P9 dependency order, **critical path**, **4-week milestone**, **completion %**, **parallel plan**, effort ledger (overlap hata kar), **open estimates (TBD)**, risk register, khule faisle, NEXT 10 ACTIONS |

---

## 2. Authoritative map — kaunsi file jeetti hai

Jab do docs ek hi cheez par **mukhtalif** hon, to yeh table faisla karta hai:

| Topic | Authoritative | Superseded / mirror | Kab |
|---|---|---|---|
| **Deployment** | **`09`** | `docs/02-DEPLOYMENT-GUIDE.md` (SUPERSEDED banner lagi hui hai) | 13 Nov 2026 |
| **Product build steps** | **`10`** | `docs/04-DEVELOPMENT-GUIDE.md` §2.3, §6, §7, §9, §10 | 13 Nov 2026 |
| **Tenant identification** | **`07`** §0, §3 | `02` ka D3/D4, §5.4, §5.4.1 | 13 Nov 2026 |
| **`GET /api/v1/plans` contract** | **`05`** §3.1.2 | `08` §6.1 (sirf mirror — pehle `05` badlein, phir yahan) | 14 Nov 2026 |
| **Migration split inventory** | **`03`** Part D | `07` §11.5 (sirf list) | 13 Nov 2026 |
| **Payment secrets / `APP_KEY`** | **`06`** §5.2 | — | 13 Nov 2026 |
| **Schedule / effort** | **`11`** | har doc ka apna §effort (source, magar overlap ke saath) | 14 Nov 2026 |
| **Tenant `.env` + `KB_*` keys** | **`09`** §7 | `08` §5.3, `10` §5.3 | 13 Nov 2026 |

### 2.1 Purani `docs/` series ka kya hua

| File | Haalat |
|---|---|
| `docs/02-DEPLOYMENT-GUIDE.md` | **SUPERSEDED** — `09` ne replace kiya. Delete **nahi** kiya, kyunke `06` §5.2.1 uske `backup.sh` ko cite karta hai. `09` §0.2 mein purane → naye section numbers ka mapping hai |
| `docs/04-DEVELOPMENT-GUIDE.md` | **Partly superseded** — §2.3 (`make:module`), §6, §7, §9, §10 ab `10` mein. §1 (clone), §3 (naming), §8 (update strategy) **abhi bhi valid** hain |
| `docs/03-INTEGRATION-GUIDE.md` | **Reconcile nahi hua** — purana product-side integration (subdomain + handoff). `08` uska path-based replacement hai. Isay cite karne se pehle `08` se cross-check karein |
| `docs/01-STATUS-REPORT.md` | Valid — `11` §1 uski jagah leta hai sirf *schedule* ke liye, *status* ke liye nahi |

---

## 3. Filename collision — dhyan rakhein

`docs/` aur `docs/platform/` dono mein `0X-*.md` naam hain, aur **numbering alag hai**:

| Number | `docs/` (purana) | `docs/platform/` (naya) |
|---|---|---|
| `01` | `01-STATUS-REPORT` | *(nahi — wohi file `docs/` mein hai)* |
| `02` | `02-DEPLOYMENT-GUIDE` ❌ superseded | `02-ARCHITECTURE-DECISIONS` |
| `03` | `03-INTEGRATION-GUIDE` ⚠️ unreconciled | `03-EXTRACTION-PLAN` |
| `04` | `04-DEVELOPMENT-GUIDE` ⚠️ partly superseded | `04-CONTROL-PLANE-DESIGN` |
| `05`–`11` | — | platform series |

**Is liye hamesha folder ke saath likhein:** `09` ka matlab `docs/platform/09-DEPLOYMENT-GUIDE.md` hai, `docs/02-DEPLOYMENT-GUIDE.md` nahi.

---

## 4. Reading order

**A. Naya banda / onboarding (poora context, ~3–4 ghante)**

```text
11 (schedule + aaj ki haalat)
 └─ 01 (status: kya bana, kya nahi)
     └─ 02 (faisle: D1–D5)
         └─ 07 (tenancy — D3/D4 ko supersede karta hai)
             └─ 04 (CP)  →  05 (identity)  →  06 (payments)
                 └─ 08 (product integration)
                     └─ 10 (naya product kaise)
                         └─ 09 (deploy)
```

**B. CP banana hai** — `04` → `05` → `06` → `09` §7 (`.env`)

**C. Product banana hai** — `10` → `08` → `07` → `09`

**D. Sirf deploy karna hai** — `09` (poora) → `11` §4.7 (phase exit criteria)

**E. "Main kahan hoon, aage kya"** — `11` §1 → `11` §8 (NEXT 10 ACTIONS)

---

## 5. Conventions — is series mein kya expect karein

| Cheez | Matlab |
|---|---|
| **Zubaan** | Roman Urdu + English technical terms. Code, identifiers, aur commands English mein |
| **File path har code block ke saath** | Jahan file **maujood nahi**, wahan `(NEW)` likha hai. Path ke baghair code block = design sketch |
| **`NOT VERIFIED`** | Claim **test nahi hua**. Fact na manein — pehle verify karein. Har doc ke §0.5 ya §9 mein list hai |
| **`§N.M`** | Usi doc ka section, jab tak doc ka naam na likha ho (`08` §2.9 = doosri file) |
| **Effort `din`** | Working days, solo dev. Har number ka doc source `11` §5 mein |
| **Changelog** | Har doc ke aakhir mein. Design badalne par wahan entry lazmi |
| **Line endings** | Pure LF. CRLF commit na karein |
| **Tables** | Backtick ke andar pipe character **na** likhein — markdown formatter usay asli column delimiter samajh kar table tor deta hai |

---

## 6. Tooling

```bash
# Ek ya zyada docs check karein
python tools/doccheck.py docs/platform/*.md
```

**Kya check hota hai:**

| Check | Kyun |
|---|---|
| Fence balance + **unlabelled fences** | Unlabelled block par syntax highlight nahi aata |
| **Backticked pipes** table rows mein | Formatter trap — table chupchaap tor deta hai |
| Table structure (header + separator + column count) | Ek galat row poora table render kharab kar deti hai |
| CRLF vs LF | Windows par `Path.write_text()` chupchaap CRLF likhta hai |
| Cyrillic/Greek **homoglyphs** | Latin `a` ki jagah Cyrillic `a` (U+0430) — dikhta ek jaisa hai, grep nahi pakadta |
| Internal `§N.M` refs vs headings | Toota cross-reference; external refs alag classify hote hain |

**Kya check nahi hota:** content ki **sahi hona**. Tool structure dekhta hai, semantics nahi. External `§` numbers (jaise `08` §2.9) manually spot-check karein — `11` §5 ka ledger bhi manually verify hua tha.

---

*Yeh index hai, spec nahi. Kisi bhi design sawal ka jawab relevant doc mein hai — yahan sirf naqsha hai.*
