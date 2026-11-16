# 11 — Master Roadmap

**Status:** Execution plan (design, code nahi)
**Date:** 14 Nov 2026 · **Revised:** 14 Nov 2026 (**v2** — critical path, 4-week milestone, completion %, parallel plan, open estimates, P5/MVP arithmetic; changelog §9)
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

### 1.1 Completion % — aaj vs har phase ke baad

**Yeh table estimate hai, claim nahi.** Har row ka **basis** likha hua hai, aur har row **ek hi** basis par hai (Days, Tables ya Screens) — do areas ka denominator hi nahi hai, wahan TBD:

| Basis | Matlab |
|---|---|
| **Days** | Us area ke documented days ka kitna hissa ho chuka — `04` §7, `05` §8, `06` §6.1, `07` §12.1, `08` §5 |
| **Tables** | `04` §1.3 ke 18 tables mein se kitne (`04` §7 ke sprint-wise list se) |
| **Screens** | `04` §7 ke 28 admin screens mein se kitne |
| **TBD** | Docs mein is area ka **denominator** nahi hai — guess nahi kiya |

"Aaj" column `docs/01-STATUS-REPORT.md` ke executive summary se hai (0 / 15 / 80 / 0 / 12 / 35 / ~24). Uske baad ka har column **PLANNING ASSUMPTION** hai, magar har number neeche diye gaye formula se nikla hai — hawa se nahi.

| Area | Basis | Aaj | P0 | P1 | P2 | P3 | P4 | P5 | P6 | P7 | Phase 2 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Control Plane** (catalog, plans, subs, billing) | Days 65 = P2 40 + P4 25 | 0 % | 0 % | 0 % | 62 % | 62 % | 100 % | 100 % | 100 % | 100 % | 100 % |
| **Identity Hub / SSO** | Days 27.5 (`05` §8) | 15 % | 15 % | 15 % | 15 % | 100 % | 100 % | 100 % | 100 % | 100 % | 100 % |
| **Starter Kit** | TBD (denominator nahi) | 80 % | 80 % | 80 % | 80 % | 80 % | 80 % | 80 % | 80 % | 80 % | 80 % |
| **Multi-tenancy** | Days 33 = D2 2.5 + P5 15.5 + P9 15 | 0 % | 8 % | 8 % | 8 % | 8 % | 8 % | 55 % | 55 % | 55 % | 100 % |
| **Admin Panel** | Screens 28 (`04` §7) | 12 % | 12 % | 12 % | 75 % | 82 % | 100 % | 100 % | 100 % | 100 % | 100 % |
| **Deployment readiness** | TBD (`09` mein estimate nahi) | 35 % | TBD | TBD | TBD | TBD | TBD | TBD | 100 % | 100 % | 100 % |
| **OVERALL** | Days 166.5 (`11` §5.3) | ~24 % | 27 % | 27 % | 45 % | 58 % | 69 % | 77 % | TBD | 100 % | — |

**Numbers kaise nikle — teen formulas:**

1. **Control Plane 62 %** = `04` §7 ka P2 (40d) ÷ (P2 40 + P4 25). `04` ka S4 (billing) aur S6 (OAuth) `06`/`05` ne replace kiye, is liye CP ka poora scope 65d hai (`11` §0.2). P3 CP ke catalog mein kuch nahi jorrta — `62 %` wahi rehta hai.
2. **Admin Panel 75 %** = `04` §7 ke screens: S1 (1–7) + S2 (8–10) + S3 (11–13, 19–20) + S7 (23–24) + S8 (25–28) = **21 / 28**. P3 mein S6 ke screens 21–22 aate hain → **23 / 28 = 82 %**. P4 mein S4 ke 14–18 → **28 / 28**.
3. **OVERALL** = `24 % + 76 % × (cumulative days ÷ 166.5)`. **Seedha `days ÷ 166.5` nahi kiya** — 166.5 **baaqi** kaam hai, total lifetime kaam nahi. Warna P0 ke baad `6 ÷ 166.5 = 4 %` aata, jo aaj ke 24 % se **neeche** hota. Cumulative days: P0 6.0, P1 7.0, P2 47.0, P3 74.5, P4 99.5, P5 115.0, P7 166.5.

**Do rows jaan boojh kar adhoore hain:**

- **Starter Kit 80 % par flat** — `03`–`09` mein is area ka koi denominator nahi. P0 ka 3.5d package work (`03` D3) isi area ka hai, magar 3.5d ko "80 % → X %" mein badalne ka koi doc number nahi. **TBD.**
- **Deployment readiness** — 35 % se 100 % ka safar `09` par hai, aur `09` mein **koi effort number nahi** (§5.5). P0 ka CI fix (vcs + `sodium`) is row ko "35 % se kuch upar" le jata hai, magar kitna — TBD.

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

### 3.1 Critical path — poora platform

**166.5 din sum hai, critical path nahi.** Critical path woh sab se lambi zanjir hai jo kisi bhi phase ko aage nahi barhne deti. §2 ka graph dependencies dikhata hai magar duration ke saath chain nahi nikalta — yeh table woh karti hai.

| # | Phase | Din | Cumulative | Float | Source |
|---|---|---|---|---|---|
| 1 | **P0** | 6.0 | 6.0 | **0** | §5.3 |
| 2 | **P2** | 40.0 | 46.0 | **0** | `04` §7 |
| 3 | **P3** | 27.5 | 73.5 | **0** | `05` §8 |
| 4 | **P6** | **TBD** | TBD | **0** | `09` — koi estimate nahi (§5.5) |
| 5 | **P7** | 51.5 | **125.0 + P6** | **0** | `08` §5 (12.5) + `10` §11 (39) |
| | **Critical path total** | | **125.0 din + P6** | | |

**Critical path se bahar (float ke saath):**

| Phase | Din | Cumulative end | Float | Kyun float hai |
|---|---|---|---|---|
| P1 | 1.0 | 7.0 | 66.5 | Sirf P7 ko chahiye, aur P7 `P6 + 73.5` par shuru hota hai |
| P4 | 25.0 | 71.0 | **2.5** | P6 ke liye zaroori hai, magar P3 se **2.5 din pehle** khatam hota hai |
| P5 | 15.5 | 21.5 | 52.0 | P2/P3 ke saath parallel chalta hai (§3.3) |

**Teen nateeje jo is table se nikalte hain:**

1. **P4 (payments) takriban critical hai — float sirf 2.5 din.** `11` §3 ise "P3 ke baad" likhta hai, magar P6 ko P3, P4 aur P5 teenon chahiye. Agar P3 do din slip kare to P4 critical path par aa jata hai, aur uske baad **payments ki ek din ki slip poore platform ki ek din ki slip** hai. Is liye P4 ke liye buffer P3 ke andar rakhna behtar hai, P4 ke andar nahi.
2. **P5 ka float sab se zyada (52 din)** — usay P2/P3 ke saath parallel chalana "achhi practice" nahi, **lazmi** hai; warna woh critical path par aa jata.
3. **P6 poora platform rok deta hai.** Deployment ka koi estimate nahi hai (`09`), magar woh critical path ke beech mein baithta hai — yani **P6 ka estimate P7 se pehle chahiye**, P6 ke waqt nahi. Yeh §5.5 ka sab se bara khula item hai.

**P2 ke andar ka critical path** (alag level, `04` §7 se): **S1 → S2 → S3 → S4 → S6**. S5 (storefront) S2 ke baad shuru ho sakta hai magar checkout S4 par rukta hai; S7/S8/S9 float rakhte hain.

### 3.2 Pehla milestone — 4 hafte

**Definition:** P0 poora (D2 + D3 + `core` v2.0.0 tagged) **+** CI `vcs` repositories par green **+** P1 skeleton **+** `04` S0 **+** `04` S1 (CP ek product ka manifest import kar ke catalog mein daalta hai) **+** CP staging par deploy.

| # | Deliverable | Din | Source |
|---|---|---|---|
| 1 | P0 — D2 split + D3 `core` v2.0.0 + D4 `UPGRADE.md` | 6.0 | §4.1 = `07` §12.1 #2 (2.5) + package (3.5) |
| 2 | CI green on `vcs` repositories (PHP 8.3 + 8.5) | — | `09` §6.1, §11.4 — **koi estimate nahi**, is liye 0 |
| 3 | P1 — `khattakbits/sso-client` skeleton | 1.0 | `08` §5 #1 |
| 4 | `04` **S0** — MySQL, `security_events`, Passport, `khattakbits:manifest` skeleton | 2.0 | `04` §7 |
| 5 | `04` **S1** — catalog 5 tables + screens 1–7 + manifest sync | 6.0 | `04` §7 |
| | **Total — docs se** | **15.0** | |
| 6 | CP staging deploy (§4.3.1) | 1.0 | **PLANNING ASSUMPTION** — `09` mein number nahi |
| | **Total** | **16.0** | |

**Calendar:** 16.0 × 1.2 (20 % buffer, `11` §5.4) = **19.2 din = 3.8 hafte ≈ 4 hafte**. Buffer ke baghair 16.0 din = 3.2 hafte — yani 4 hafte **buffer ke saath** hain, buffer ke baghair nahi.

**Exit criteria — command, opinion nahi:**

```bash
php artisan test --filter=MigrationSplitTest          # green (03 D2)
php artisan migrate --pretend | grep -c "create table" # CP par 23 — §4.1.1 dekhein
php artisan khattakbits:manifest --check               # 10 §4.3
php artisan test --filter=ManifestImportTest           # V1–V4, 10 §4.6.3
ls -la vendor/khattakbits/                             # directory, symlink NAHI (09 §6.4)
curl -sI https://staging.khattakbits.com/up | head -1  # 200
```

**Purana milestone idea kyun nahi:** "SSO login kaam kare + tenant provisioned ho + product `read_only` ho" — yeh sunne mein chhota lagta hai, magar uske liye poora CP ka core, poora OAuth layer, aur poora P5 chahiye:

| # | Task | Din | Source |
|---|---|---|---|
| 1 | P0 | 6.0 | §5.3 |
| 2 | P1 skeleton | 1.0 | `08` §5 #1 |
| 3 | `04` S0 | 2.0 | `04` §7 |
| 4 | `04` S1 (catalog) | 6.0 | `04` §7 |
| 5 | `04` S2 (plans + entitlements) | 6.0 | `04` §7 |
| 6 | `04` S3 (tenants + subscriptions + provisioning client) | 7.0 | `04` §7 |
| 7 | `05` I0 (Passport + `sodium` + core release) | 2.0 | `05` §8 |
| 8 | `05` I1 (clients, allowlist, PKCE) | 4.0 | `05` §8 |
| 9 | `05` I2 (OIDC, JWKS, discovery) | 3.0 | `05` §8 |
| 10 | `05` I3 (login interrupt) | 3.0 | `05` §8 |
| 11 | `05` I4 (resolve, entitlements, changes feed) | 4.5 | `05` §8 |
| 12 | `05` I8 (`users/tenants` — SSO callback ke baad picker iske baghair nahi chalta) | 1.0 | `05` §8 |
| 13 | P5 (path-based tenancy, 18 items − P0 ka hissa) | 15.5 | §4.6 |
| | **Total** | **61.0** | |

**61.0 din = 12.2 hafte solo; 20 % buffer ke saath 73.2 din ≈ 14.6 hafte ≈ 3.5 mahine.** Yani "SSO + provisioned + `read_only`" **4 hafte ka milestone nahi, ek quarter ka** hai — aur usmein storefront, payments, webhooks, hardening kuch bhi nahi.

### 3.3 Parallel plan — solo vs 2 devs

**Solo:** koi phase parallel nahi — §5.3 ka 166.5 din sequential hai. **2 devs:** har phase ke andar kya saath chal sakta hai, woh docs khud batate hain; jahan nahi batate wahan TBD.

| Phase | Solo | Andar kya parallel chalta hai | 2 devs | Source |
|---|---|---|---|---|
| P0 | 6.0 | **Kuch nahi** — D2/D3 package contract torte hain, sequence lazmi hai | 6.0 | `11` §4.1 ("Parallel: kuch nahi") |
| P1 | 1.0 | — | 1.0 | `08` §5 |
| P2 | 40.0 | S5 / S6 / S7 parallel; **S1 → S2 → S3 → S4 sequential** | **~27.5** | `04` §7 ("2 devs par ~5.5 hafte") |
| P3 | 27.5 | I2 ∥ I3, I5 ∥ I6; **I1 → I4 sequential** | **~15.0** | `05` §8 ("2 devs par ~3 hafte") |
| P4 | 25.0 | P1.2 ∥ P1.3; P1.4 P1.0 ke baad independent | **~15.0** | `06` §6.1 ("2 devs par ~3 hafte") |
| P5 | 15.5 | P2/P3 ke saath **phase-level** parallel (`11` §3) | **TBD** | `07` §12.1 mein 2-dev number nahi |
| P6 | **TBD** | — | **TBD** | `09` |
| P7 | 51.5 | 7a (wiring) ∥ 7b (product modules) | **TBD** | `08` §5, `10` §11 mein 2-dev number nahi |
| P8 | 20.0 | P9 ke saath parallel | **TBD** | `06` §6.2 |
| P9 | 15.0 | P8 ke saath parallel | **TBD** | `07` §13.8 |

**Do phase-level parallelisms jo calendar badalte hain** (`11` §3):

| Jo saath chalta hai | Kyun safe hai | Kitna bachta hai |
|---|---|---|
| **P2 ∥ P5** | P5 product repo hai, P2 CP repo — sirf `GET /api/v1/users/tenants` par milte hain (woh P3/I8 mein) | P5 ka poora 15.5 |
| **P8 ∥ P9** | Payments Phase 2 aur custom domains ka koi shared file nahi | chhota |

**2-dev total: 93.2 din** (`11` §5.4, ratio 0.56) — **PLANNING ASSUMPTION**, kyunke 0.56 teen alag ratios (`04` 0.55, `05` 0.55, `06` 0.60) ka mean hai, kisi doc ne poore platform ka 2-dev number nahi diya. Upar ki per-phase rows jama karne se 2-dev number **93.2 se thoda kam** aata hai (P5/P6/P7 ka TBD hai), is liye 93.2 ko **upper bound** samjhein.

**2 devs par kya *nahi* tez hota:**

- **P0** — ek hi package contract hai; do log ek hi `UPGRADE.md` likh rahe hon to ulta slow.
- **P6** — deployment ek server par hota hai; parallel karne ke liye do environments chahiye, jo khud kaam hai.
- **P7b** — pehla product ek hi product hai; modules parallel ho sakte hain magar manifest/entitlement contract pehle tay hona chahiye (`10` §4).

### 3.4 Alternative ordering — "vertical slice first"

**Masla:** §3 ka order CP ko **poora** banata hai (S0–S9, 40 din), phir identity (27.5), phir product (15.5), phir wiring (51.5). Yani pehla **asli** loop — ek tenant, SSO se login, `read_only` gate — **~96 din + P6** baad chalta hai. Us waqt tak koi bhi cheez asli user ke haath mein nahi hoti.

**Alternative:** patla vertical slice pehle — poora CP nahi, sirf utna jitna loop ke liye chahiye.

| Step | Kya | Din | Source |
|---|---|---|---|
| 1 | P0 — baseline + D2 + D3 | 6.0 | §5.3 |
| 2 | P1 — `sso-client` skeleton | 1.0 | `08` §5 #1 |
| 3 | CP-lite: **S0 + S1 + S2 + S3** (storefront/payments/webhooks/hardening **nahi**) | 21.0 | `04` §7 (2+6+6+7) |
| 4 | Identity: **I0–I4 + I8** | 17.5 | `05` §8 (2+4+3+3+4.5+1) |
| 5 | P5 — path-based tenancy | 15.5 | §4.6 |
| | **CP-side loop (ek test tenant, `tenants:provision` se)** | **61.0** | |
| 6 | `08` §5 #2–#5 — product side: PKCE, mirror, resolve ladder, guards | 6.0 | `08` §5 (1.5+1.5+1.5+1.0) |
| | **End-to-end loop (product SSO se login karta hai, `read_only` lagta hai)** | **67.0** | |

**Wohi loop, maujooda order par:**

| Step | Kya | Din | Source |
|---|---|---|---|
| 1 | P0 + P1 | 7.0 | §5.3, `08` §5 #1 |
| 2 | P2 poora (S0–S9) | 40.0 | `04` §7 |
| 3 | P3 poora (I0–I9) | 27.5 | `05` §8 |
| 4 | P5 | 15.5 | §4.6 |
| 5 | P6 | **TBD** | `09` |
| 6 | Product wiring (wahi 6.0 din) | 6.0 | `08` §5 #2–#5 |
| | **End-to-end loop** | **96.0 + P6** | |

**Farq: 67.0 vs 96.0 + P6 → ~29 din (≈ 6 hafte) pehle.** Aur yeh farq **kam** nahi hota — `09` ka P6 estimate jitna bhi ho, woh maujooda order mein loop se **pehle** aata hai, vertical slice mein **baad** mein.

**Kya peeche chhoot jata hai (aur uski qeemat):**

| Chhoda | Din | Qeemat |
|---|---|---|
| `04` S4 → `06` P1.2–P1.4 (billing) | 6 → 13 | Loop ke liye zaroori nahi; subscription manually S3 ke screens 11–13 se ban jati hai |
| `04` S5 (storefront) | 6.0 | Koi self-serve nahi — onboarding manual |
| `04` S7 (webhooks) + `05` I5 | 4 + 3 | `tenant.provisioned` product tak nahi pahunchta → `tenants:provision` manually (`07` §12.1 #6) |
| `04` S8 (access screens) | 4.0 | `kb_user_product_access` baad mein — data model par asar padega |
| `04` S9 + `05` I7 (hardening + tests) | 5 + 3 | **Sab se mehnga trade-off**: `GetRoutesAreReadOnlyTest` gate (`05` §7.1) late aata hai, aur tests ka bojh end par |
| `05` I6, I9 | 3 + 1 | **Loop** ke liye zaroori nahi — magar I6 **MVP** ke liye blocking hai (§5.4), is liye yeh slice se MVP tak ka farq hai |

**Ordering badalti hai, total nahi:** 166.5 din wahi rehta hai (§5.3). Vertical slice sirf **risk ka distribution** badalta hai — pehla asli loop 96 + P6 din ke bajaye **67 din (≈ 13 hafte)** par aata hai, magar aakhri 30 din mein teen badi cheezein (storefront, webhooks, hardening) ek saath aati hain. **Pehla milestone** (§3.2) dono orders mein wahi rehta hai — 4 hafte — kyunke woh P0/S1 tak hi jaata hai.

---

## 4. Phases — tafseel

### 4.1 P0 — Baseline, D2 split, `core` v2.0.0 — 6.0 din

**Kyun sab se pehle:** teenon cheezein **package ka public contract** torti hain. Inhein baad mein karna = har product ko dobara migrate karna. `03` Part D ka apna rule: "package release se **pehle**, kyunke woh package ka public contract tor rahe hain."

| # | Deliverable | Source |
|---|---|---|
| 1 | `git tag pre-tenancy` (aaj sirf `pre-extraction` hai) | `03` Part D |
| 2 | `database/migrations/{central,tenant}/` — **4 central, 11 tenant** | `03` D2 |
| 3 | `CoreServiceProvider`: **central** paths `loadMigrationsFrom()`, **tenant** paths `tenancy.migration_parameters['--path']` mein — tenant ke liye `loadMigrationsFrom()` **nahi** | `03` D2.1, `07` §7.3 |
| 3b | **CP single-DB flag** — tenancy na ho to tenant paths bhi chalein (§4.1.1) | **GAP — `03` D2 mein nahi** |
| 4 | `core` routes ka split — `attachments` + `document-activity` `{tenant}` ke andar | `03` D3.1, `07` §3.4 |
| 5 | `menu.php` contract mein `path` key (static) + `href` (runtime) | `03` D3.2, `07` §5.3 |
| 6 | `khattakbits/core` **v2.0.0** tag + `UPGRADE.md` (codemod C1–C6) | `03` D3, D4 |
| 7 | `MigrationSplitTest` (permanent) | `03` D2, `10` §2.5 |

> **Row 3 pehle galat likha hua tha** — "do `loadMigrationsFrom()`" `03` D2.1 ke trap #1 ke bilkul khilaf hai: tenant paths ko `loadMigrationsFrom()` se register karna unhein `php artisan migrate` par **central DB** mein chala dega. Sahi shakal row 3 mein hai.

**Effort:** `07` §12.1 #2 = **2.5d** (isme Step 0 cross-DB FK shamil hai jo `03` D1 mein **ho chuka** — is liye asli bacha hua kaam is se kam hai, magar doc ne alag number nahi diya) + package work **3.5d** (`07` §12.1, "~3.5 din ismein shamil hain") + row 3b ka **TBD** (§4.1.1).

**Done when:**

```bash
# 1. Split sahi hai — tenant DB mein users NAHI, attachments HAI
php artisan test --filter=MigrationSplitTest

# 2. Cross-DB FK zero (D1 ka guard, hamesha)
grep -rn "constrained('users')\|references('users')" \
  database/migrations vendor/khattakbits/core/database/migrations
# → tenant tables mein zero hit

# 3. Files — folder ke hisaab se (03 D2.3)
ls vendor/khattakbits/core/database/migrations/central/*.php | wc -l   # → 2
ls vendor/khattakbits/core/database/migrations/tenant/*.php  | wc -l   # → 10
ls database/migrations/central/*.php | wc -l                           # → 2
ls database/migrations/tenant/*.php  | wc -l                           # → 1
# → 4 central files, 11 tenant files (+1 module = 12)

# 4. Tables — asli schema, temporary SQLite par (pretend nahi)
DB_CONNECTION=sqlite DB_DATABASE=/tmp/kb-schema.sqlite php artisan migrate:fresh --force
# → 23 tables = 8 central + 14 tenant + 1 module, KB_TENANCY_ENABLED=false

# 5. Purana behaviour toota nahi
php artisan test && vendor/bin/pint --test

# 6. D3 — flag off par route table bilkul wohi (koi {tenant} nahi)
php artisan route:list --json | jq -r '.[] | select(.uri | contains("{tenant}")) | .uri'
# → khali output; core ke 15 routes (9 central + 6 tenant-scoped) bina prefix
php artisan test --filter=CoreRoutesTest

# 7. D3 — menu contract: har item par path, koi purana href nahi
grep -rn "'href' =>" app/Modules/*/menu.php          # → zero hit
php artisan test --filter=MenuBuilderTest

# 8. Tags maujood
git tag -l 'pre-*'   # → pre-extraction, pre-tenancy
git tag -l 'v2.0.0'  # → khattakbits/core v2.0.0 (annotated)
```

> **`migrate --pretend | grep -c "create table"` (v2) bhi galat tha** — woh statements ginta hai, aur `--pretend` ka output DB ke already-migrated state par depend karta hai (fresh DB par 23, migrated DB par 0). Sahi tareeqa: **files** folder ke hisaab se ginne, aur **tables** ek temporary SQLite `migrate:fresh` se. Wajah aur poora mechanism §4.1.1 / `03` D2.3 mein.

**Parallel:** kuch nahi — yeh blocking hai.

#### 4.1.1 CP single-DB — tenant migrations kaise chalti hain? (**RK21 — fixed by flag**)

**Do facts jo takraate hain:**

| Fact | Source |
|---|---|
| CP **single DB** hai, `stancl/tenancy` CP mein **nahi** | `04` §7 scope note: "`stancl/tenancy` **is CP mein nahi** — CP single DB hai" |
| Tenant migrations `loadMigrationsFrom()` se register **nahi** hotin; sirf `tenancy.migration_parameters['--path']` mein jati hain, aur woh block `class_exists(\Stancl\Tenancy\Tenancy::class)` ke andar hai | `03` D2.1, `07` §7.3 |

**Nateeja:** CP mein tenancy package nahi → `class_exists` false → woh block **skip** → tenant paths kahin register nahi → `php artisan migrate` sirf `central/` chalata hai. CP ke paas `audit_logs`, `settings`, `email_accounts`, `email_logs`, `email_templates`, `attachments`, `documents`, `permissions`/`roles` — **kuch nahi**. Pehla page render 500 dega (`Table 'audit_logs' doesn't exist`).

**`07` §7.3 ka note 2 is maamle mein ghalat tha:** "Tenancy na ho to yeh block skip ho jata hai aur starter kit single-tenant chalta rehta hai." Skip hona **sahi** hai, magar single-tenant app ko un tables ki zaroorat **hoti hai** — woh sirf "ek tenant" hai, "zero tables" nahi.

**Faisla (v3) — Option A, config flag:**

| Key | Env | Default |
|---|---|---|
| `khattakbits.tenancy.enabled` | `KB_TENANCY_ENABLED` | `false` |

`false` → `php artisan migrate` **dono** folders chalata hai (CP / starter kit / tests, ek DB). `true` → sirf central, aur tenant folders `tenancy.migration_parameters['--path']` se `tenants:migrate` ko. `class_exists` guard **hata diya gaya** — package ki maujoodgi config nahi honi chahiye. Poora spec `03` D2.3 mein hai.

**Do jagah registration — dono lazmi:**

| Kaun | Kya | Kahan |
|---|---|---|
| Package | core `central/` + `tenant/` + module paths | `CoreServiceProvider::boot()` |
| **Host** | `database/migrations/{central,tenant}` | `AppServiceProvider::boot()` |

Laravel `database/migrations/*.php` glob karta hai, subfolders **nahi** — host ki 3 migrations (cache, jobs, permissions) register na karne par khamoshi se gayab ho jati hain.

| App | `php artisan migrate` | `tenants:migrate` |
|---|---|---|
| **CP** (`enabled=false`) | **23** tables = 8 central + 14 tenant + 1 module | — |
| **Product** (`enabled=true`) | 8 central + modules ka central hissa | 14 tenant + modules ka tenant hissa |

**Filename immutability is kaam ko sasta karti hai.** `03` D2.2 kehta hai folder badle, filename na badle. CP ki `migrations` table mein purane naam already hain, aur naye folder mein **wohi** naam hain — Laravel migration ko **basename** se match karta hai, path se nahi. Is liye CP par sirf **path registration** chahiye, koi data migration ya rename nahi.

**Tests (P0 mein, `03` D2.3):** `MigrationSplitTest` (folder counts + FK scan), `CentralSchemaParityTest` (flag off → 23 tables), `MigrationPathsTest` (dono flag values). Do-tenant wala asli split test **P5** mein aata hai, kyunke us waqt tak `tenants:migrate` maujood nahi hota.

**Effort: TBD.** `03` D2 ka 2.5d sirf split + Step 0 cover karta hai; yeh flag + uske tests usmein nahi hain. Chhota kaam lagta hai (ek config key + provider ki ek branch + ek test), magar **guess nahi karunga** — `03` D2 mein add hone ke baad hi number aayega.

**Test jo abhi maujood nahi:** `MigrationSplitTest` (`03` D2.2) sirf **product** side prove karta hai (tenant DB mein `users` nahi, `attachments` hai). CP ke liye alag test chahiye — masalan `CentralSchemaParityTest`: CP par `migrate` chalayein aur assert karein ke `audit_logs`, `settings`, `attachments`, `documents`, `email_*`, `permissions` sab maujood hain. **Yeh test P0 ke scope mein add karna hoga, warna CP tootne ka pata staging par chalega.**

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

**S6 (5d) kahan gaya:** `05` I1–I4 wohi kaam karta hai (Passport clients, redirect allowlist, PKCE, `/oauth/userinfo`, `resolve` ETag/304, `renew-link`) — **14.5 din mein** (`05` §8: 4 + 3 + 3 + 4.5), kyunke `05` ne poora OIDC layer + login interrupt + users-changes feed add kiya.

**Staging gate — S1 ke baad:** CP staging par deploy hota hai (§4.3.1), aur uske baad **har phase ka "done when" staging par bhi true hona chahiye**.

**Done when:**

```bash
# 18 CP tables — absolute count se compare na karein (core + module tables bhi isi DB mein hain)
php artisan test --filter=ManifestImportTest                   # V1–V4 green (10 §4.6.3)
php artisan platform:rehash --dry-run                          # idempotent: second run 0 changes
php artisan db:seed --class=DemoProductSeeder                  # 1 product, 4 modules, 3 plans
php artisan test --filter=GetRoutesAreReadOnlyTest             # 05 §7.1 gate
php artisan tinker --execute 'echo Schema::hasTable("product_reports") ? "ok" : "missing";'
```

> **`→ 18` (v1) galat tha:** `grep -c "create table"` CP ki **saari** tables ginta hai — 18 CP tables + core central (8) + module tables. Is liye `Schema::hasTable()` se assert karein, absolute number se nahi (module add hone par number badalta rehta hai).

| Test | Kya prove karta hai |
|---|---|
| `ManifestImportTest` | Module/feature absent → `status=removed`, **delete nahi** (`04` §6.2) |
| `EntitlementHashTest` | Canonical JSON + `sha256` stable — do runs, same hash (`04` §3.3) |
| `SubscriptionStateMachineTest` | `pending → active → past_due → expired` — illegal transitions `422` |
| `GetRoutesAreReadOnlyTest` | Koi GET route data nahi likhta (`05` §7.1) |

**Parallel:** P5 (tenancy) — sirf `GET /api/v1/users/tenants` par milta hai jo P3 mein hai.

#### 4.3.1 Staging gate — S1 ke baad, hamesha ke liye

**Rule:** `04` S1 khatam hone ke foran baad CP staging par deploy hota hai. Uske baad **har phase ka "done when" staging par bhi pass hona chahiye** — local `php artisan test` green hona kaafi nahi.

**Kyun S1 ke baad, S0 ke baad nahi:** S0 mein deploy karne ko kuch nahi (sirf Passport + ek command skeleton). S1 pehla sprint hai jo asli product data banata hai, aur uske baad har sprint (S2, S3, S5…) usi DB par migrate karta hai — staging woh jagah hai jahan **migration + deploy ka order pehli dafa asli MySQL par** chalta hai, kyunke tests SQLite par chalte hain.

**Effort: 1.0 din — PLANNING ASSUMPTION.** `09` mein **koi estimate nahi** hai (§5.5). 1.0d sirf **pehli dafa** ka hai (`09` §1–§4 server prep + `09` §8.2 clone → composer → key → migrate); har agla deploy `09` §11 ke CI/CD se hota hai aur uski mehnat phase ke andar nahi gini gayi.

**Checklist (S1 ke baad, aur har phase ke baad):**

```bash
curl -sI https://staging.khattakbits.com/up | head -1   # 200
php artisan about --only=environment                    # production, debug=false
php artisan migrate:status | grep -c Pending            # 0
```

**Staging par woh teen `NOT VERIFIED` items bhi test hote hain** jo `09` §0.5 mein hain — `GRANT CREATE ON \`prefix\_t\_%\`.*` ka probe (`09` §3.3 khud kehta hai "scratch/staging MySQL par, production par NAHI"), certbot deploy-hook reload, aur `tenants:migrate` rollback. Yeh production par pehli dafa test karna matlab production ko test environment banana.

**Kya staging gate *nahi* hai:** yeh acceptance test nahi, sirf **smoke** hai — functional assertions abhi bhi `php artisan test` se aati hain. Iska kaam sirf ek cheez pakadna hai: "local par chalta hai, server par nahi" wali class (`.env`, queue, MySQL vs SQLite, `storage` permissions).

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

**`07` §12.1 ke 18 items.** Har row ke do hisse **alag columns** mein hain — kaunsa hissa P0 (D2/D3) mein chala gaya aur kaunsa P5 mein raha. Purani table sab kuch ek column mein mila deti thi, is liye woh **19.5** par ruk jati thi jabke P5 **15.5** hai.

| # | Kaam | `07` §12.1 | P0 (D2/D3) | P5 |
|---|---|---|---|---|
| 1 | Package install, `config/tenancy.php`, 4 overrides (O1–O4), `SlugPathTenantResolver` | 1.5 | — | 1.5 |
| **2** | **Step 0 cross-DB FK (5 migrations) + `central/`/`tenant/` folder split** | 2.5 | **2.5** (D2) | **0** |
| 3 | Path identification: route restructure, middleware order, `EnsureTenantMembership`, `onFail` | 2.0 | — | 2.0 |
| 4 | Reserved slug list + coverage test + slug validation + create flow | 1.0 | — | 1.0 |
| 5 | `tenantUrl()` (PHP + shared prop + JS) + codemod C1–C6 + CI gate † | 2.0 | package | 2.0 |
| 6 | Provisioning job + provisioner + `tenants:provision`/`destroy` + rollback | 2.0 | — | 2.0 |
| 7 | `ModuleRegistry::migrationPaths()` + provider change + module layout † | 1.0 | package | 1.0 |
| 8 | Per-tenant: cache prefix, storage, mailer, scheduler, queue pinning | 1.5 | — | 1.5 |
| 9 | Permissions: `Role`/`Permission` connection + `PermissionRegistrar` cache key † | 0.5 | package | 0.5 |
| 10 | Ops: backup script, export, suspend, DB privileges, deploy runbook | 1.5 | — | 1.5 |
| 11 | Tests T1–T20 + `TenantTestCase` | 2.0 | — | 2.0 |
| 12 | Documentation + `02`/`04`/`05` corrections | 1.0 | — | 1.0 |
| 13–17 | Owner mapping, `failed_at_state` resume, sweeper, DB privileges, membership reconcile † | 2.5 | package | 2.5 |
| 18 | Tests T21–T38 | 1.0 | — | 1.0 |
| | **Rows ka sum (18 items)** | **22.0** | **2.5** | **19.5** |
| | **Package work** — `07` §12.1 note: "#5, #7, #9, #17 ka helper" ke andar shamil | — | **3.5** (D3) | **−3.5** |
| | **`07` rounding** — rows 22.0 vs stated `Total` 21.5 | — | — | **−0.5** |
| | **Total** | **21.5** | **6.0** | **15.5** |

**† ka matlab:** package work ka hissa **isi row ke andar** hai (`07` §12.1: "~3.5 din ismein shamil hain (#5, #7, #9, #17 ka helper)"). `07` ne sirf **total 3.5** diya hai, yeh nahi ke kaunsa row kitna — **per-row split TBD** (§5.5). Is liye table mein 3.5 ek **alag negative row** hai, chaar rows ke andar chhupa hua nahi.

**Teen numbers jo ab khud bolte hain:**

1. **P0 ka hissa 6.0** = #2 (2.5, D2) + package (3.5, D3) — §4.1 ke 6.0 se exactly match.
2. **P5 = 19.5 − 3.5 − 0.5 = 15.5** — doc ke `Total` se match, aur ab **rows jama karne se bhi** wohi aata hai.
3. **`07` ka apna mismatch:** uske rows **22.0** bante hain, `Total` **21.5** likha hai — 0.5 ka farq. Hum doc ke `Total` ko authority mante hain (§0.2), is liye −0.5 ek **explicit row** hai, chhupa hua rounding nahi. Yeh `07` mein theek hona chahiye (§5.5).

**#2 ke baare mein ek shart:** usmein Step 0 (cross-DB FK, `03` D1) **shamil** hai jo pehle hi ho chuka. Yani asli bacha hua kaam 2.5 se **kam** hai, magar `07` ne alag number nahi diya — is liye 2.5 hi rakha (§9 ka `NOT VERIFIED` item).

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

**Critical path (P7 ke andar):** `make:module` v2 (`10` §6) → pehla module → manifest sync (`10` §4) → CP registration → SSO (`08` §2) → entitlement gate → UAT. **Poore platform ka critical path §3.1 mein hai** — yeh sirf P7 ka andaruni order hai.

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
| 1 | `04` S6 (5d) | `05` I1–I4 (**14.5d**) | **−5.0** |
| 2 | `04` S4 (6d) | `06` P1.2–P1.4 (13d) | **−6.0** |
| 3 | `07` #2 (2.5d) | `03` D2 → P0 | **−2.5** |
| 4 | `07` package (3.5d) | `03` D3 → P0 | **−3.5** |
| | | **Total overlap** | **−17.0** |

> **Row 1 mein `12.5d` (v1) galat tha — `05` I1–I4 = 14.5d** (`05` §8: I1 4 + I2 3 + I3 3 + I4 4.5). Delta phir bhi **−5.0** hai, kyunke delta `04` ke S6 ki 5d se aata hai (woh 5d hat gaye), `05` ke 14.5 se nahi. Is liye `11` §4.3 ka "12.5 din mein" bhi 14.5 kar diya gaya. **Kul total par is tabdeeli ka koi asar nahi** — 166.5 wahi hai.

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

**Agar sirf MVP chahiye** (ek product, ek tenant, manual TRX, SSO) — v1 ka hisaab adhoora tha, is liye poora dobara:

| # | Hissa | Din | Source |
|---|---|---|---|
| 1 | P0 — baseline + D2 + D3 | 6.0 | §5.3 |
| 2 | **P1** — `sso-client` skeleton (v1 mein **ghayab** tha) | **1.0** | `08` §5 #1 |
| 3 | **`04` S0** — MySQL, Passport, manifest skeleton (v1 mein **ghayab** tha, magar S1–S3 iske baghair nahi chalte) | **2.0** | `04` §7 |
| 4 | `04` S1–S3 — catalog, plans, tenants + subscriptions | 19.0 | `04` §7 (6+6+7) |
| 5 | `05` I0–I4 — Passport, OIDC, login interrupt, resolve | 16.5 | `05` §8 (2+4+3+3+4.5) |
| 6 | **`05` I8** — `GET /api/v1/users/tenants` (v1 mein **ghayab** tha; SSO callback ke baad picker iske baghair nahi chalta, `07` §6.6.3) | **1.0** | `05` §8 |
| 7 | **`05` I6** — disable propagation (v1 mein **ghayab** tha; iske baghair `is_active=false` product tak nahi pahunchta) | **3.0** | `05` §8 |
| 8 | `06` P1.0–P1.4 — manual TRX + verification + invoice | 19.5 | `06` §6.1 (4.5+2+4.5+5.5+3) |
| 9 | P5 — path-based tenancy | 15.5 | §4.6 |
| 10 | P7 — product wiring + pehla product | 51.5 | `08` §5 (12.5) + `10` §11 (39) |
| | **MVP total** | **135.0** | |

**v1 ka 128 se 135 kaise:** +1.0 (P1) +2.0 (S0) +1.0 (I8) +3.0 (I6) = **+7.0**. Chaaron **blocking** hain, "nice to have" nahi: S0 ke baghair S1–S3 nahi chalte, I8 ke baghair SSO callback ke baad picker dead-end hai, I6 ke baghair disable propagation ka poora design (`05` §5) MVP se bahar reh jata hai.

**Calendar:** 135.0 ÷ 5 = **27 hafte solo**; 20 % buffer ke saath **32.4 hafte** (~7.5 mahine). Phir bhi 6+ mahine — kyunke CP ke bina koi product nahi, aur identity ke bina CP ka koi matlab nahi.

### 5.5 Open estimates — jo docs mein nahi hain

**Yeh section guess nahi karta.** Jo number kisi doc mein nahi hai, woh yahan **TBD** hai — aur uska asar likha hai taake aap faisla kar sakein ke estimate pehle chahiye ya baad mein.

| # | Item | Kya missing | Doc | Asar agar galat ho | Default |
|---|---|---|---|---|---|
| **1** | **P6 deployment** | **Koi estimate hi nahi** — `09` mein ek bhi din ka number nahi | `09` (poora) | P6 **critical path par** hai (§3.1), is liye uski har slip seedha P7 ko lagti hai | **TBD** — P3/P4 ke beech chahiye, P6 ke waqt nahi |
| **2** | **`07` §12.1 ka table mismatch** | Rows **22.0** bante hain, `Total` **21.5** likha hai (0.5) | `07` §12.1 | P5 15.5 ke bajaye 16.0 hota; ledger ka 166.5 0.5 badal jata | 21.5 (doc `Total`) — §4.6 mein −0.5 explicit row |
| **3** | **`07` #2 ka 2.5d** | Step 0 cross-DB FK **shamil** hai jo `03` D1 mein ho chuka — bacha hua kaam kam, magar naya number nahi | `07` §12.1 #2, `03` D1 | P0 6.0 zyada dikh raha hai (over-estimate, khatra nahi) | 2.5 (doc number) |
| **4** | **Package 3.5d ka per-row split** | `07` kehta hai "#5, #7, #9, #17 ke andar", magar kaunsa row kitna — nahi | `07` §12.1 note | P5/P0 ka split per-row galat ho sakta hai (total theek) | Total 3.5 — §4.6 mein alag negative row |
| **5** | **2-dev ratio 0.56** | Teen alag ratios (0.55/0.55/0.60) ka **mean** — poore platform ka 2-dev number kisi doc mein nahi | `04` §7, `05` §8, `06` §6.1 | 93.2 din ka 2-dev claim ±10 % ho sakta hai | 0.56, **PLANNING ASSUMPTION** |
| **6** | **P5 / P6 / P7 ka 2-dev number** | `07`, `09`, `08`, `10` mein 2-dev split nahi | — | §3.3 ki rows TBD hain, is liye 93.2 **upper bound** hai | TBD |
| **7** | **Staging deploy** | `09` staging ko sirf "restore test" ke liye mention karta hai, estimate nahi | `09` §1.1, §8 | §3.2 ka 16.0 din 1.0 din se kam/zyada | **1.0d PLANNING ASSUMPTION** (§4.3.1) |
| **8** | **P0 ka CP single-DB flag** | `03` D2 mein yeh case hai hi nahi (§4.1.1) | `03` D2.1, `07` §7.3 | CP boot hi nahi hoga — **blocking**, effort chhota | **TBD** — `03` D2 mein add karein |
| **9** | **`06` §6.1 ka 21.5 vs 25** | Section ka heading "~21.5 din" kehta hai, rows **25.0** banti hain, aur doc khud "25 din (21.5 + buffer)" likhta hai | `06` §6.1 | P4 25 ya 21.5 — 3.5 ka farq | 25.0 (rows) |
| **10** | **`06` §6.2 ka 17 vs 20** | Heading "~17 din", rows **20.0**, total line "20 din effort" | `06` §6.2 | P8 3 din over/under | 20.0 (rows) |
| **11** | **`04` S4 vs `06` P1.2–P1.4** | S4 = 6d, `06` ka replacement = 13d. Agar payments descope ho jaye to CP ko S4 (6d) chahiye — us variant ka koi number nahi | `04` §7, `06` §6.1 | Payments hatane se 19 din nahi bachte, 12 bachte hain | TBD |
| **12** | **P9 ka overlap analysis** | `07` §13.8 = 15d, magar `tenantUrl()` extension (#4) P5 #5 par depend karta hai — double count hua ya nahi, likha nahi | `07` §13.8 | P9 15 se kam ho sakta hai | 15.0 (doc) |
| **13** | **P7b ke 39 din** | `10` §11 = "`04-DEVELOPMENT-GUIDE` §10 ka 50 − 11", magar item-wise breakdown `10` mein nahi — aur woh purana doc **partly superseded** hai (`README` §2.1) | `10` §11 | P7 ka sab se bara hissa (39 / 51.5) sab se kam verified hai | 39.0 (doc) |
| **14** | **CI + secrets setup (P0)** | `09` §6/§11.4 mechanics deta hai, waqt nahi | `09` §11.4 | P0 6.0 se bahar | TBD |

**Sab se ahem teen:** #1 (P6 — critical path par, zero estimate), #8 (CP single-DB flag — CP boot hi nahi hoga), #2 (`07` ka 0.5 mismatch — chhota magar ledger mein permanent).

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
| **RK21** | **CP single DB mein tenant migrations kabhi nahi chaltin** — `class_exists(\Stancl\Tenancy\Tenancy::class)` guard CP mein false hai, is liye `audit_logs`, `settings`, `email_*`, `attachments`, `documents`, `permissions` nahi banti aur CP ka pehla page 500 deta hai | P0, P2 | **Critical** (CP boot hi nahi hota) | **Fixed (v3):** `khattakbits.tenancy.enabled` flag (`03` D2.3) + host provider registration + `MigrationSplitTest` / `CentralSchemaParityTest` / `MigrationPathsTest` | `03` D2.3, `07` §7.3, `04` §7 scope note |

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
| **7** | **D2 branch** — 4 central / 11 tenant split + `MigrationSplitTest` + **CP single-DB flag** (§4.1.1) | P0 ka pehla hissa; P5 iske baghair start nahi ho sakta — **aur flag ke baghair CP boot hi nahi hoga** | `03` D2 inventory; `10` §2.5 ka test; `03` D2 mein flag add karein | 2.5 din + **TBD** |
| **8** | **D3 `core` v2.0.0** — routes split + `menu.php` `path` + `UPGRADE.md` | P0 ka doosra hissa; `menu.php` contract sab products ko todta hai | `03` D3.1/D3.2/D4 | 3.5 din |
| **9** | **`04` §1.3 ke 18 tables ke migrations** (S1 se shuru) | CP ka pehla sprint; `05`/`06` dono in tables par depend hain | `04` §1.3 + `04` §7 S1 | 2 din |
| **10** | **Pehla product ka faisla** — Mizaan ya LawBits | P7 ka scope isse tay hota hai; `08` Mizaan par likha hai | Faisla + `08` §1 ka seeder re-key (agar LawBits) | 1 ghanta |

**Is hafte ka target:** #1, #2, #3, #4, #6, #10 (sab chhote) + #5 shuru. **#7–#9 agle hafte** — woh P0 ka asli kaam hai.

---

## 9. Changelog

### 16 Nov 2026 — v5 (D3/D4 done: core v2.0.0 tagged)

| # | Kya | Kyun |
|---|---|---|
| 1 | **Routes split live** — `KhattakBits\Core\Routing\CoreRoutes`: central 9 + tenant-scoped 6; tenant group `/{tenant}` ke andar **sirf** flag `true` par | Flag `false` (CP, starter kit, tests) par `route:list` diff = **121 routes, 0 added, 0 removed** — aaj ke URLs bilkul wohi |
| 2 | **`menu.php` contract** — `path` (static, slug-free) + `href` (runtime); MenuBuilder payload mein dono, module entry par bhi; `path` ka fallback `href` (purani menu chalti rehti hai) | `AccountingDemo/routes.php` `href` par route register karta tha — slug `href` mein aane se route registration mein bake ho jata (tenant A ki routes, tenant B ki 404) |
| 3 | **`mergeConfigFrom` docblock theek** — `config:cache` package defaults ko nahi girata | P0a mein verify hua tha; docblock ulta keh raha tha |
| 4 | **`UPGRADE.md` + `CHANGELOG.md`** (core), **tag `v2.0.0`** (annotated, local — push nahi) | `03` D4; upgrade ke baghair koi product v2 par nahi jayega |
| 5 | **Tests: 138 → 154** (689 → 998 assertions); pint host 77 / core 60 files PASS | `CoreRoutesTest` (9), `MenuBuilderTest` (7) |

> **`tenantUrl()` / `tenantPath()` is version mein nahi hain** — `07` §5.2 ke woh helpers P5
> (tenancy phase) mein aate hain. Is liye `MenuBuilder::hrefFor()` abhi `path` return karta hai
> (ek seam), aur `UPGRADE.md` C1–C3/C6 ko "P5" mark karta hai, C4/C5 ko "ab". Jhoot bol kar
> upgrade doc mein aise APIs likhna ke woh maujood nahi, product teams ko phansata.

### 16 Nov 2026 — v4 (D2 implemented: split live, D1 ke 2 FK band)

| # | Kya | Kyun |
|---|---|---|
| 1 | **D2 code mukammal** — core mein 12 migrations (`2 central / 10 tenant`), host mein 3 (`2/1`), module ki 1 → `Database/Migrations/tenant/`; `MigrationPaths` helper + `ModuleRegistry::migrationPaths()` + dono providers mein registration | Spec (`03` D2.3) ke mutabiq; `KB_TENANCY_ENABLED=false` par `migrate:fresh` = **23 tables** (asli SQLite par verify) |
| 2 | **D1 ke do cross-DB FK pakde gaye** — `email_templates.updated_by` aur `DocumentColumns::add()` ka `created_by` dono `users` par `constrained()` thay | D1 ki list 5 tables ki thi; `email_templates` aur doctype tables us waqt maujood nahi thay. Multi-DB tenancy mein `tenants:migrate` MySQL par fail hota. Detail `03` D2.3.1 |
| 3 | **`DocumentColumns` shared helper** — ek fix se har future doctype theek; `MigrationSplitTest` ab discovery-based FK scan karta hai (fixed list nahi) | Warna naya doctype phir wohi bug le aata aur test use pakadta hi nahi |
| 4 | **P0 done-when asli numbers ke saath** — 4 central / 11 tenant (+1 module = 12) files, 23 tables (8 central + 14 tenant + 1 module) | v3 ne mechanism diya tha, numbers verify nahi hue thay. Ab test inhi numbers ko lock karta hai |
| 5 | **Tests: 117 → 138** (588 → 689 assertions) | `MigrationPathsTest` (7), `MigrationSplitTest` (11), `CentralSchemaParityTest` (3) |

### 14 Nov 2026 — v3 (D2 spec: RK21 band, done-when theek)

| # | Kya | Kyun |
|---|---|---|
| 1 | **RK21 ka fix spec** — `khattakbits.tenancy.enabled` (`KB_TENANCY_ENABLED`, default `false`) ne `class_exists(\Stancl\Tenancy\Tenancy::class)` guard replace kiya; `03` D2.3 mein poora mechanism, `07` §7.3 mein code | v2 ne gap **pakda** tha magar mechanism TBD tha. Ab `false` par dono folders chalti hain (CP ek DB), `true` par sirf central + `--path` |
| 2 | **Host registration likh diya** — `database/migrations/{central,tenant}` `AppServiceProvider::boot()` mein; Laravel subfolders khud nahi uthata | Yeh trap `03` D2 mein bhi nahi tha: move ke baad host ki 3 migrations khamoshi se gayab hotin |
| 3 | **P0 done-when dobara** — `migrate --pretend` ka table-count hata kar **file counts** + temporary SQLite `migrate:fresh` table list | v2 ne command ki galti pakdi thi magar usi command ko theek karke rakha; `--pretend` migrated DB par 0 deta hai, is liye woh test hi nahi tha |
| 4 | §4.1.1 `GAP` → `RK21 — fixed by flag`; tests ki list (P0 ke 3, P5 ka do-tenant wala) | Ab gap khula nahi, spec band hai |
| 5 | **Effort note:** flag `07` §12.1 #2 ke 2.5d mein shamil nahi tha — §4.1 ki row 3b **TBD** rehti hai | Number guess nahi kiya; asli delta implementation ke baad changelog mein |

### 14 Nov 2026 — v2 (structure + arithmetic)

| # | Kya | Kyun |
|---|---|---|
| 1 | **§1.1 Completion %** — `01` ke 7 areas, aaj vs har phase, har row ka **basis** likha hua (days / tables / screens / TBD) | "Kitna ho gaya" ka koi jawaab roadmap mein nahi tha; aur `01` ke 24 % ko 166.5 din ke saath jodne ka tareeqa bhi nahi tha |
| 2 | **§3.1 Critical path** — poore platform ka, sirf P7 ka nahi: P0 → P2 → P3 → P6 → P7 = **125.0 + P6**; P4 ka float sirf **2.5 din** | 166.5 **sum** hai, critical path nahi. P4 (payments) takriban critical hai, aur P6 beech mein baith kar sab rokta hai |
| 3 | **§3.2 Pehla milestone — 4 hafte** — P0 + CI + P1 + S0 + S1 = **15.0d** (+1.0 staging) ≈ 4 hafte | Purana "SSO + provisioned + `read_only`" milestone **61.0d ≈ 3.5 mahine** ka tha — 4 hafte ka nahi. Ab dono ka hisaab saath hai |
| 4 | **§3.3 Parallel plan** — per phase kya saath chalta hai, solo vs 2 devs, doc citation ke saath | `11` §3 ka "Parallel with" column phase-level tha, phase ke **andar** ka nahi. 2-dev 93.2 ka **upper bound** hona bhi ab likha hua hai |
| 5 | **§3.4 Alternative ordering — vertical slice first** — pehla asli loop **67.0d** vs maujooda order **96.0 + P6** (~29 din farq) | Poora CP pehle banane ki wajah se pehla feedback 4+ mahine baad aata tha; ab trade-off table ke saath dono raaste likhe hain |
| 6 | **§4.1.1 CP single-DB GAP** — tenant migrations CP mein **kabhi nahi** chaltin (`class_exists` guard), is liye CP boot hi nahi hoga | `03` D2 aur `07` §7.3 ne yeh case likha hi nahi; `07` §7.3 ka note ("single-tenant chalta rehta hai") **ghalat** hai. Teen options, ek recommended (config flag) |
| 7 | **P0 done-when theek** — `→ 4` / `→ 11` / `→ 18` galat thay (migration files vs `create table` statements) | Aaj split se pehle hi `migrate --pretend` ka `create table` count **23** deta hai (8 central + 14 tenant + 1 module). Absolute count par test likhna hi galat tha |
| 8 | **§4.6 P5 table dobara** — P0 ka hissa aur P5 ka hissa **alag columns**; package 3.5 aur `07` ka 0.5 **explicit rows** | Purani table 19.5 par ruk jati thi jabke P5 15.5 hai. Ab dono taraf se sum match karta hai (21.5 / 6.0 / 15.5) |
| 9 | **§5.2 ledger row 1: 12.5 → 14.5** | `05` I1–I4 = 4+3+3+4.5 = **14.5**. Delta −5.0 wahi rehta hai (woh S6 se aata hai), total 166.5 par koi asar nahi |
| 10 | **§5.4 MVP dobara** — +P1 (1.0), +S0 (2.0), +I8 (1.0), +I6 (3.0) → **135.0d ≈ 27 hafte** | Chaaron blocking thay: S0 ke baghair S1–S3 nahi, I8 ke baghair SSO callback dead-end, I6 ke baghair disable propagation MVP se bahar |
| 11 | **§5.5 Open estimates** — 14 items, har ek ke saath asar aur default | Numbers jo docs mein nahi hain woh bikhre hue thay; P6 (critical path par, zero estimate) sab se bara |
| 12 | **Staging gate (§4.3.1)** — S1 ke baad deploy, har phase ka done-when staging par bhi | `09` staging ko sirf restore test ke liye mention karta hai; "local green, server 500" wali class sirf staging pakadti hai |

**v2 mein jo tabdeel nahi hua:** koi bhi **total** nahi badla — 166.5, 138.5, −17.0, 61.0, 93.2 sab wahi hain. Sirf **structure, splits aur ghalat commands** theek hue. Naye numbers sirf teen hain aur teenon **naye** hisse hain: 4-week milestone 15.0 (+1.0 assumption), vertical slice 67.0 vs 96.0, MVP 135.0.

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

- **`09` (deployment) ka effort** — `09` mein koi din ka number nahi hai, is liye `NOT ESTIMATED` likha. Guess nahi kiya. P6 critical path par hai (§3.1), is liye yeh sab se mehnga khula item hai.
- **2-dev ratio 0.56** — `04`/`05`/`06` ke teen alag ratios (0.55, 0.55, 0.60) ka mean hai, kisi doc ne poore platform ka 2-dev number nahi diya. §3.3 ka 93.2 is liye **upper bound** hai.
- **`07` §12.1 #2 ka 2.5d** — usmein Step 0 (cross-DB FK) shamil hai jo `03` D1 mein **ho chuka**. Asli bacha hua kaam is se kam hai, magar doc ne alag number nahi diya — is liye 2.5 hi rakha.
- **MySQL grants probe** — `NOT VERIFIED` (`09` §0.5), P6 ka blocking item. Staging par test hota hai (§4.3.1).
- **CP single-DB flag ka effort** — `03` D2 mein case hi nahi hai, is liye **TBD** (§4.1.1). Mechanism recommend kiya hai, number nahi.
- **§1.1 ka completion %** — `01` ke "aaj" ke ilawa baaki columns **PLANNING ASSUMPTION** hain. Basis har row mein likha hai; jahan denominator nahi tha (Starter Kit, Deployment) wahan **TBD** hai.
- **§3.2 ka staging 1.0d** — **PLANNING ASSUMPTION**, `09` mein staging ka koi estimate nahi.
- **Split ke baad ka `create table` count** — 23 **aaj** ka verified number hai (14 Nov 2026). Split ke baad CP par wohi 23 rehna chahiye (same migrations, sirf folder badla) — yeh **verify karna hai**, kyunke module add hone par badlega.

**Verification log (14 Nov 2026):**

```bash
php artisan migrate --pretend | grep -c "create table"   # → 23
# tables: 8 central + 14 tenant + 1 module (demo_entries), 15 migration files se
```

---

*Yeh roadmap design + schedule hai, code nahi. Har phase ka "done when" command ya test hai — "kaam ho gaya lagta hai" is document mein valid nahi. Effort numbers `02`–`10` se hain, jahan overlap tha wahan §5.2 mein khula hua hai, aur jahan number hi nahi tha wahan §5.5 mein **TBD** likha hai — guess kahin nahi kiya.*
