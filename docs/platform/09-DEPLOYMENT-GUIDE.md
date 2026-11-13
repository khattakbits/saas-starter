# 09 — Deployment Guide (Self-hosted Ubuntu 24.04, path-based tenancy)

**Date:** 14 Nov 2026 · **Version:** v1
**Repo:** `E:/LaraProjects/saas-starter` (branch `extract/package-skeleton`)
**Depends on:** `07-MULTI-TENANCY-DESIGN.md` (§2.4 connection pinning, §3 path routes, §3.5 slug rules, §6 provisioning, §8.1 prefix, §10.1–§10.2 DB users/migrate), `05-IDENTITY-HUB-SPEC.md` (§0.2.1 Passport pin, §0.2.2 CI/server prep, §3.1.2 plans, §3.8 rate limits), `06-PAYMENTS-DESIGN.md` (§5.2.1 `APP_KEY`, **§5.2.2 the 10 deployment entries**), `04-CONTROL-PLANE-DESIGN.md` (§1.3 tables, §5.3 provisioning sequence), `08-EXAMPLE-PRODUCT-INTEGRATION.md` (§2 sso-client, `config/kb.php`), `02-ARCHITECTURE-DECISIONS.md` (§0 D1–D5)
**Supersedes:** `docs/02-DEPLOYMENT-GUIDE.md` (12 Nov 2026) — **wahi authoritative nahi hai** (§0.1)
**Rule:** Har command copy-paste ke qabil hai. Jo cheez test nahi hui us par **`NOT VERIFIED`** likha hai — usay production mein assume na karein.

**Part A** (§1–§8): server se pehle successful deploy tak.
**Part B** (§9–§16): queues, scheduler, zero-downtime, CI/CD, backups, monitoring, checklist, failures.

---

## 0. Authority, scope, aur jo badla

### 0.1 Kaunsi file authoritative hai

| File | Status | Kyun |
|---|---|---|
| **`docs/platform/09-DEPLOYMENT-GUIDE.md`** (yeh file) | ✅ **AUTHORITATIVE** | Path-based tenancy (14 Nov decision), MySQL, per-product DB users, sso-client, provisioning queue — sab ismein |
| `docs/02-DEPLOYMENT-GUIDE.md` | ❌ **SUPERSEDED** 14 Nov 2026 | 12 Nov ko likhi gayi thi — **path-based tenancy se pehle**. Wildcard subdomain blocks, DNS-01 wildcard certs, PostgreSQL recommendation, ek `kb_app` user with `ALL PRIVILEGES` — sab us decision se mar chuka hai |

`02` ko delete nahi kiya — us par banner laga diya hai. Wajah: uske §11 ka `backup.sh` **`06` §5.2.1 mein cited** hai (APP_KEY aur backup bucket ka trust-boundary argument), aur `06` §5.2.2 us ke section numbers reference karta hai. Banner + mapping table (§0.2) se purane references tootte nahi.

**`06` §5.2.2 ki 10 entries** — kahan address hui hain, yeh table us contract ka proof hai:

| # | `06` §5.2.2 ne kaha | `09` mein kahan |
|---|---|---|
| 1 | `.env` templates: `APP_PREVIOUS_KEYS=` (khali, commented), `PAYMENTS_PHASE=1` | **§7.1**, **§7.2** |
| 2 | Backups: `APP_KEY` ki ek copy backup bucket se **bahar** (password manager) | **§12.3** |
| 3 | Backups: attachments backup already hai, magar payment proofs **1 saal** retention ke saath align karein | **§12.1**, **§12.5** |
| 4 | Security checklist: `APP_KEY` offline copy, 2 logon ke paas, 6-mahine test | **§14.3** |
| 5 | Security checklist: `APP_PREVIOUS_KEYS` khali hai (rotation ke ilawa) | **§14.3** |
| 6 | Security checklist: `payment_gateway_accounts.credentials` encrypt hai (tinker se verify) | **§14.3** |
| 7 | Scheduler: `subscriptions:remind`, `payments:sla-alert`, `payments:prune-callbacks` | **§10.1** |
| 8 | Supervisor: `QUEUE_CONNECTION` `database` ho, `sync` nahi | **§7.1**, **§9.0** |
| 9 | Naya section: **Key rotation runbook** (5-step, maintenance window) | **§12.4** |
| 10 | Monitoring: `payment.verification_pending_count`, `payment.oldest_pending_age_hours` | **§13.3** |

### 0.2 Purane section numbers ka mapping (jo `06`/`02` mein cite hue hain)

`06` §5.2.2 `docs/02-DEPLOYMENT-GUIDE.md` ke §7.1, §9, §10, §11, §13, §16 ko naam se pukarta hai. Woh numbers ab is file par is tarah map hote hain:

| Purana `02` | Naya `09` | Mawad |
|---|---|---|
| §0 sizing | §1.1 | Sizing table |
| §1 server prep | §1.2–§1.8 | OS, user, SSH, UFW, fail2ban, swap, TZ, sodium |
| §2 PHP | §2.1–§2.2, §2.8 | PHP + extensions, OPcache, FPM pools |
| §3 Composer/Node/DB/Redis | §2.3–§2.7 | Composer, Node, **MySQL**, Redis, Supervisor |
| §4 directory layout | §8.1 | `/var/www/{app}/{releases,shared,current}` |
| §5 Nginx | §4 | Server blocks — **wildcard block khatam** |
| §6 SSL | §5 | certbot **HTTP-01**, per domain |
| §7.1 / §7.2 `.env` | §7.1 / §7.2 | CP aur product templates |
| §8 pehla deploy | §8.2–§8.6 | Clone → migrate → Passport → admin → first tenant |
| §9 Supervisor | §9 | Chaar CP programs + do product programs |
| §10 Scheduler | §10 | CP vs product split |
| §11 Backups | §12 | **Central + saare tenant DBs** |
| §12 logrotate | §13.2 | — |
| §13 Monitoring | §13 | + payment metrics (§13.3) |
| §14 Deployer | §11 | + `tenants:migrate` pipeline mein |
| §15 GitHub Actions | §11.4 | + `sodium` + private packages |
| §16 checklist | §14 | 6 sub-sections |
| §17 failures | §15 | + tenancy/Passport failures |

### 0.3 Yeh guide kya assume karti hai (final decisions)

| Decision | Value | Asar |
|---|---|---|
| **Tenancy model** | **Path-based** — `https://mizaanbitserp.com/{tenant_slug}/...` | Ek origin, ek cookie, **ek certificate per product domain** |
| Wildcard DNS | ❌ Nahi | Koi `*.mizaanbitserp.com` A record nahi |
| Wildcard TLS | ❌ Nahi | Koi DNS-01 challenge nahi, koi Cloudflare API token nahi |
| Handoff | ❌ Nahi (`02` §5.4.1 **retired** 13 Nov 2026) | Tenant switch = same session, koi redirect nahi (`07` §3.7) |
| Custom domains (`erp.acme.com`) | **Phase 2** | §5.4 mein optional |
| Database | **MySQL 8.0** (Ubuntu 24.04 default) | `07` §8.1, §10.2 |
| DB users | **Per-product**: `mizaan_app`, `mizaan_provision`, `mizaan_backup` | `07` §10.2.1 |
| DB prefix | `mizaan_t_`, `lawbits_t_` | `07` §8.1 |
| Server layout | **1 server**: CP + 1 product | §0.4 |
| PHP | **8.5** (constraint `^8.3`) | `composer.json` |
| Packages | `khattakbits/core`, `khattakbits/sso-client`, `@khattakbits/ui` — **private VCS** | §6 |

### 0.4 Ek server abhi, split baad mein

```text
┌─ kb-prod-01 (Ubuntu 24.04, 4 vCPU / 8 GB) ────────────────────────┐
│                                                                   │
│  khattakbits.com          → /var/www/khattakbits   (FPM pool: kb)  │
│  mizaanbitserp.com        → /var/www/mizaanbits    (FPM pool: mizaan)│
│    └── /{tenant}/...      ← same app, same pool, path-based        │
│                                                                   │
│  MySQL 8.0 ─┬─ khattakbits          (CP central)                   │
│             ├─ mizaan_central       (product central)              │
│             └─ mizaan_t_*           (tenant DBs, N of them)        │
│                                                                   │
│  Redis (cache/session/queue) · Nginx · Supervisor · certbot        │
└───────────────────────────────────────────────────────────────────┘
```

**Split kab karein** (aur kya move hota hai):

| Signal | Kya alag server par jaye | Kya same rehta hai |
|---|---|---|
| Tenant DBs > 200, ya `innodb_buffer_pool_size` RAM se barhne lage | **MySQL** — dedicated DB server, `@'10.0.0.%'` grants already iske liye likhe hain (`07` §10.2.2) | App servers |
| Ek product ka traffic doosre ko slow kare | **Product app** — apna server, apna FPM pool | CP, MySQL |
| 2+ products | Har product apna server | CP + MySQL + Redis |
| Redis par `maxmemory` evictions queue jobs gira rahe hon | **Redis** — alag instance per app (`REDIS_PREFIX` already distinct hai) | Baaki |

Split ke waqt **sirf teen cheezein** badalti hain: `.env` mein `DB_HOST`/`REDIS_HOST`, MySQL grants ka host part (`@'127.0.0.1'` → `@'10.0.0.%'`), aur Nginx `fastcgi_pass` (unix socket → `10.0.0.5:9000`). Deployer ka `host()` block. **Code mein kuch nahi.**

### 0.5 Verification legend

| Marker | Matlab |
|---|---|
| **VERIFIED** | Is repo mein file/line dekh kar confirm kiya, ya command chala kar output dekha |
| **NOT VERIFIED** | Design/assumption hai, production se pehle **khud test karein**. Aisa har item §16 mein dobara listed hai |
| **NEW** | Yeh cheez repo mein abhi maujood nahi — banani hai |

Aaj ke `NOT VERIFIED` items (poori list):

1. **§3.3** — kya `GRANT CREATE ON \`mizaan\_t\_%\`.*` `CREATE DATABASE` ke liye kaafi hai (MySQL 8.0). **Sab se ahem.**
2. **§5.3** — certbot `--deploy-hook` par `php8.5-fpm` reload ka exact behaviour jab ek hi pool do apps serve kar rahe hon.
3. **§11.2** — `tenants:migrate` ka rollback behaviour agar release fail ho (script `down` nahi karta, magar migration half-applied reh sakti hai).
4. **§12.2** — `SHOW DATABASES` se prefix match karke dump karna jab koi tenant DB `DROP` ho chuki ho magar `tenants` row bachi ho.

---

# PART A — Server se pehle deploy tak

## 1. Server prep

### 1.1 Sizing

| Tier | Spec | Kya chalega | Note |
|---|---|---|---|
| Dev / staging | 2 vCPU, 4 GB, 40 GB SSD | 1 CP + 1 product, < 20 tenants | Swap 2 GB lazmi |
| **Production start** | **4 vCPU, 8 GB, 80 GB NVMe** | **1 CP + 1 product, ~200 tenants** | Yeh target hai |
| Growth | 8 vCPU, 16 GB + separate DB server | 3 products, 1000+ tenants | §0.4 |
| Per 100 extra tenants | + 1 GB RAM, + 10 GB disk | — | Dump size ~ 5–20 MB/tenant |

**MySQL ko kitni RAM:** `innodb_buffer_pool_size` = **50–60% of RAM**. 8 GB box par `4G`. Iske baghair 200 tenant DBs par har query disk se aayegi.

**Disk:** `df -h` par 20% free rakhein. Backup `/var/backups/kb` **usi disk par** likhta hai (§12) — 30% se zyada bhare to off-site copy fail hogi.

**3D login:** Three.js client-side hai, server CPU par asar **nahi**. Magar bundle ~600 KB — HTTP/2 aur immutable cache headers lazmi (§4.1, §14.5).

### 1.2 Base OS — 24.04 vs 22.04

```bash
# ── Timezone (pehle, warna logs ka time galat) ──────────────────────────────
sudo timedatectl set-timezone Asia/Karachi
timedatectl status

# ── Hostname ────────────────────────────────────────────────────────────────
sudo hostnamectl set-hostname kb-prod-01

# ── Base packages ───────────────────────────────────────────────────────────
sudo apt update && sudo apt -y upgrade
sudo apt -y install curl wget git unzip zip htop ncdu tree jq \
  software-properties-common ca-certificates gnupg lsb-release \
  ufw fail2ban unattended-upgrades apt-listchanges
```

**Ubuntu 24.04 vs 22.04 — jo actually farq karta hai:**

| Cheez | 24.04 LTS | 22.04 LTS | Asar |
|---|---|---|---|
| PHP default | 8.3 | 8.1 | Dono par `ondrej/php` PPA se 8.5 chahiye (§2.1) |
| MySQL default | 8.0.36 | 8.0.36 | Same. 8.4 chahiye to `mysql-apt-config` (optional) |
| Nginx | 1.24 | 1.18 | **`http2 on;` directive sirf 1.25.1+ par hai** — 1.18 par `listen 443 ssl http2;` likhna padega (§4.2 note) |
| systemd | 255 | 249 | `fail2ban` ka `backend = systemd` dono par theek |
| OpenSSL | 3.0.13 | 3.0.2 | Koi farq nahi |
| `python3-certbot-nginx` | 2.9 | 1.21 | Dono theek |

> **22.04 par Nginx:** `http2 on;` line **hata dein** aur `listen 443 ssl;` ko `listen 443 ssl http2;` kar dein. Warna `nginx -t` error dega: `unknown directive "http2"`.

### 1.3 Deploy user + SSH key-only

```bash
# ── Dedicated deploy user (sudo nahi, sirf ek sudoers entry — §11.5) ────────
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy

# ── Apni local machine par (server par nahi) ───────────────────────────────
#   ssh-keygen -t ed25519 -C "deploy@kb-prod-01" -f ~/.ssh/kb_prod_ed25519
#   ssh-copy-id -i ~/.ssh/kb_prod_ed25519.pub deploy@203.0.113.10

# ── SSH hardening — ROOT LOGIN AUR PASSWORD OFF ────────────────────────────
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
sudo tee /etc/ssh/sshd_config.d/99-hardening.conf > /dev/null <<'EOF'
Port 22
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
AllowUsers deploy
EOF
sudo sshd -t && sudo systemctl restart ssh

# ── VERIFY (naya terminal kholein — purana band NA karein) ──────────────────
ssh -i ~/.ssh/kb_prod_ed25519 deploy@203.0.113.10 'whoami && sudo -n true && echo SUDO_OK'
```

> **`AllowUsers deploy`** — `root` aur baaki sab blocked. Agar aap `ubuntu` user se login karte hain to usay bhi list mein add karein, warna lockout. **Naya terminal se verify kiye baghair purana SSH session band na karein.**

### 1.4 UFW + fail2ban

```bash
# ── UFW — sirf 22/80/443 ────────────────────────────────────────────────────
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp comment 'SSH'
sudo ufw allow 80/tcp comment 'ACME HTTP-01 + redirect'
sudo ufw allow 443/tcp comment 'HTTPS'
sudo ufw --force enable
sudo ufw status verbose

# ── fail2ban — SSH brute force ──────────────────────────────────────────────
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd
ignoreip = 127.0.0.1/8 ::1

[sshd]
enabled = true
port    = 22
EOF
sudo systemctl enable --now fail2ban
sudo fail2ban-client status sshd
```

> **MySQL port 3306 kabhi UFW se na kholein.** App `127.0.0.1` se connect karta hai (§3.2 grants `@'127.0.0.1'`). Split server ho tab `sudo ufw allow from 10.0.0.0/24 to any port 3306`.
>
> **Redis port 6379 bhi na kholein** — `bind 127.0.0.1` already hai (§2.6).

**App-level login throttle already maujood hai** — `CoreServiceProvider` mein `throttle:login` = 5/min (email+IP). `05` §3.8 mein `POST /login` 5/min listed hai. fail2ban sirf SSH ke liye; HTTP brute force ke liye Nginx `limit_req` hai (§4.5).

### 1.5 unattended-upgrades (security patches)

```bash
sudo tee /etc/apt/apt.conf.d/20auto-upgrades > /dev/null <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF

sudo tee /etc/apt/apt.conf.d/51unattended-upgrades-kb > /dev/null <<'EOF'
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}";
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
// PHP aur MySQL ko auto-upgrade NA karein — woh maintenance window ka kaam hai
Unattended-Upgrade::Package-Blacklist {
    "php8.5";
    "mysql-server";
    "nginx";
};
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Mail "ops@khattakbits.com";
EOF

sudo unattended-upgrade --dry-run -d 2>&1 | tail -20
```

> **PHP/MySQL/Nginx ko blacklist kyun:** inka auto-upgrade FPM reload karta hai jo **beech deploy** mein aa sakta hai, aur MySQL minor upgrade restart maangta hai (downtime). Inhe `06` §2.3 ke maintenance window mein manually karein.

### 1.6 Swap (8 GB RAM par bhi)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
sudo sysctl vm.swappiness=10
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
free -h
```

> **`swappiness=10` kyun:** default 60 par kernel aggressively swap karta hai, aur MySQL/OPcache ke pages swap hone se latency 10× ho jati hai. 10 = sirf emergency.

### 1.7 Timezone — pura stack

```bash
# OS
sudo timedatectl set-timezone Asia/Karachi

# PHP (php.ini default UTC hota hai, jo Laravel ke APP_TIMEZONE se override hota hai)
php -i | grep -i "date.timezone"     # date.timezone => UTC => UTC (theek hai, Laravel khud handle karta hai)

# MySQL (backup timestamps aur NOW() ke liye)
sudo mysql -e "SET GLOBAL time_zone = '+05:00';"
sudo tee /etc/mysql/mysql.conf.d/kb.cnf > /dev/null <<'EOF'
[mysqld]
default-time-zone = '+05:00'
EOF
```

> **Laravel `APP_TIMEZONE=Asia/Karachi` set karta hai**, is liye app ke `now()` theek chalte hain. MySQL ka `default-time-zone` sirf `NOW()` aur dump timestamps ke liye hai. `+05:00` likhein, `Asia/Karachi` **nahi** — MySQL ko named timezone tables chahiye (`mysql_tzinfo_to_sql`) jo default install mein nahi hoti.

### 1.8 `ext-sodium` gate — Passport iske baghair install hi nahi hota

`05` §0.1 ka verified chain: `laravel/passport 13.10.*` → `league/oauth2-server ^9.2` → `lcobucci/jwt ^5.6` → **`ext-sodium *` (hard require)**. `lcobucci/jwt 5.6` ne sodium polyfill hata diya.

```bash
# ── Deploy se PEHLE. Teen commands, teenon pass honi chahiye ────────────────
php -m | grep sodium
# → sodium                      ← output aana LAZMI. Khali = Passport install fail

php -r 'var_dump(function_exists("sodium_crypto_sign_verify_detached"));'
# → bool(true)

php -r 'var_dump(extension_loaded("openssl"));'
# → bool(true)                  ← RS256 JWT signing iske baghair nahi chalta
```

Agar `php -m | grep sodium` khali hai:

```bash
sudo apt -y install php8.5-sodium
sudo phpenmod sodium
sudo systemctl restart php8.5-fpm
php -m | grep sodium          # dobara — ab output aana chahiye
```

> **Yeh local machine par bhi lazmi hai** — `05` §0.2 mein verified: Windows par `C:\php\php.ini:945` par `;extension=sodium` commented tha, DLL maujood thi. Server par PPA package `php8.5-sodium` deta hai, magar **verify kiye baghair aage na barhein** — `composer install` 60 second baad fail karega aur wajah samajh nahi aayegi.

**Deploy ke baad (aur CI mein) runtime check:**

```bash
composer check-platform-reqs
# → ext-sodium  *  success
# Agar sodium gayab ho to YEH command deploy fail karegi — behtar hai ke pehli
# OAuth request par 500 na aaye (05 §0.2.2 ka point).
```

---

## 2. Stack

### 2.1 PHP 8.5 + extensions

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt -y install \
  php8.5-fpm php8.5-cli php8.5-common \
  php8.5-curl php8.5-mbstring php8.5-xml php8.5-zip \
  php8.5-bcmath php8.5-intl php8.5-gd \
  php8.5-mysql php8.5-sodium \
  php8.5-redis \
  php8.5-opcache

php -v
# → PHP 8.5.x (cli)

# ── Har zaroori extension ek hi command se verify ───────────────────────────
php -m | grep -E '^(pdo_mysql|mysqli|sodium|openssl|redis|intl|gd|zip|bcmath|mbstring|opcache)$' | sort
```

Expected output (11 lines):

```text
bcmath
gd
intl
mbstring
mysqli
openssl
opcache
pdo_mysql
redis
sodium
zip
```

**Kya kyun:**

| Extension | Kis liye | Kiske baghair kya tootega |
|---|---|---|
| `pdo_mysql` | MySQL | Kuch bhi nahi chalega |
| **`sodium`** | Passport → `lcobucci/jwt` | **`composer install` fail**, `05` §0.1 |
| `openssl` | RS256 JWT signing, TLS, `encrypted` cast | Passport keys, HTTPS, `payment_gateway_accounts.credentials` |
| `redis` | Cache, session, queue (§2.6) | `CACHE_STORE=redis` par `Class "Redis" not found` |
| `intl` | `Number::currency()`, PKR formatting | Invoice amounts |
| `gd` | Attachment thumbnails | Upload preview |
| `bcmath` | Money math (`06` §2.1) | Rounding errors |
| `opcache` | §2.2 | 3–5× slower |
| `zip` | Composer dist install | `composer install` unzip fallback par slow |
| `mbstring` | Slug, `Str::slug` (`07` §3.5) | Slug generation |

> **`php8.5-sqlite3` install na karein production par.** Tests SQLite use karte hain, production MySQL. SQLite production par hona sirf ek extra attack surface hai.

**`php.ini` — `/etc/php/8.5/fpm/conf.d/99-kb.ini` aur `/etc/php/8.5/cli/conf.d/99-kb.ini` dono:**

```bash
for SAPI in fpm cli; do
sudo tee /etc/php/8.5/$SAPI/conf.d/99-kb.ini > /dev/null <<'EOF'
expose_php = Off
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 12M
post_max_size = 12M
max_input_vars = 3000
date.timezone = UTC
opcache.enable = 1
EOF
done
sudo systemctl restart php8.5-fpm
```

> **`upload_max_filesize=12M` kyun:** `config/attachments.php` ka `max_kb => 10240` (10 MB). 12M headroom deta hai. Nginx `client_max_body_size` bhi **12M** hona chahiye warna 413 aayega (§4.2) — teen jagah (PHP FPM, PHP CLI, Nginx) same value.

### 2.2 OPcache

`/etc/php/8.5/mods-available/opcache.ini`:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.jit=off
opcache.jit_buffer_size=0
```

```bash
sudo phpenmod opcache && sudo systemctl restart php8.5-fpm
php -i | grep -E "opcache.(enable|validate_timestamps|memory_consumption)"
```

**Do load-bearing consequences:**

1. **`validate_timestamps=0`** → code change **automatically pick nahi hota**. Har deploy par `systemctl reload php8.5-fpm` **lazmi** (§11.1 ka Deployer task, aur certbot hook nahi).
2. **`$realpath_root`** Nginx mein (§4.2) isi ke saath kaam karta hai — naya release = naya real path = naya opcache entry. Agar `$document_root` use karein to symlink resolve nahi hota aur opcache purana script serve karta rehta hai.

> **`opcache.jit=off` kyun:** JIT Laravel par 2–5% deta hai magar `opcache.max_accelerated_files` aur memory ko unpredictable bana deta hai, aur PHP 8.5 par JIT + `preload` ke saath rare segfault reports hain. Stability > 3%.

### 2.3 Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php
composer --version
# → Composer version 2.10.x
```

**Composer config — server par global (deploy user ke liye):**

```bash
sudo -u deploy composer global config --no-plugins allow-plugins.php-http/discovery true
sudo -u deploy composer global config process-timeout 900
sudo -u deploy composer global config optimize-autoloader true
sudo -u deploy composer global config preferred-install dist
```

> **`process-timeout 900`** — private VCS repos se clone karte waqt default 300s kaafi nahi hota (GitHub slow ho to `The process timed out` aata hai, jo ghalat message hai).

### 2.4 Node 22 (sirf build ke liye, ya CI mein)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt -y install nodejs
node -v && npm -v
# → v22.x / 10.x
```

> **Recommendation: build CI mein karein** (§11.4). Server par Node sirf fallback hai. `npm ci` + `vite build` 8 GB box par ~400 MB RAM leta hai — queue workers ke saath chalne par OOM ka risk hai. Agar CI build use karein to server par Node **install na karein**.

### 2.5 MySQL 8.0 — PostgreSQL **nahi**

**Purana `02` §3 PostgreSQL recommend karta tha. Woh superseded hai.** Wajah:

| `07` §8.1 ka decision | PostgreSQL ke saath |
|---|---|
| `stancl/tenancy` + `MySQLDatabaseManager` | Postgres ke liye `PostgresDatabaseManager` hai, magar |
| **`tenancy.database.prefix` + `GRANT` pattern** (`07` §10.2.2) | **Postgres mein per-database `GRANT` pattern nahi hai** — privileges database-level objects par hote hain, `dbname_%` wildcard nahi. Yeh poora §10.2 ka isolation model Postgres par **kaam nahi karta** |
| `CREATE DATABASE` per tenant | Postgres mein `CREATE DATABASE` **transaction ke andar nahi** chalta — provisioning ka atomicity model badal jata hai |
| Tests `sqlite :memory:` | Same, koi farq nahi |

Yani MySQL choice **preference nahi, `07` §10.2 ke grant-pattern isolation ka requirement** hai. Postgres ka RLS argument (jo purane guide mein tha) path-based tenancy ke saath bekaar hai — hum "single DB, shared schema" **nahi** kar rahe, per-tenant DB kar rahe hain.

```bash
sudo apt -y install mysql-server
sudo systemctl enable --now mysql
sudo mysql --version
# → mysql  Ver 8.0.36 (Ubuntu 24.04 default)
```

**`/etc/mysql/mysql.conf.d/kb.cnf`:**

```bash
sudo tee /etc/mysql/mysql.conf.d/kb.cnf > /dev/null <<'EOF'
[mysqld]
# ── Memory (8 GB box; RAM ka 50-60%) ───────────────────────────────────────
innodb_buffer_pool_size         = 4G
innodb_buffer_pool_instances    = 4
innodb_log_file_size            = 512M
innodb_flush_log_at_trx_commit  = 2
innodb_flush_method             = O_DIRECT

# ── Connections (2 FPM pools + 8 workers + cron) ───────────────────────────
max_connections                 = 200
max_connect_errors              = 1000

# ── Per-tenant DBs: table cache aur file handles ───────────────────────────
table_open_cache                = 4000
table_definition_cache          = 2000
open_files_limit                = 20000

# ── Charset (Laravel default) ──────────────────────────────────────────────
character-set-server            = utf8mb4
collation-server                = utf8mb4_unicode_ci

# ── Safety ─────────────────────────────────────────────────────────────────
local_infile                    = 0
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION

# ── Slow query — 500ms threshold, log_queries_not_using_indexes off ────────
slow_query_log                  = 1
slow_query_log_file             = /var/log/mysql/slow.log
long_query_time                 = 0.5
log_queries_not_using_indexes   = 0

# ── Timezone (1.7) ─────────────────────────────────────────────────────────
default-time-zone               = '+05:00'

# ── Bind: sirf localhost (split server par 10.0.0.1) ───────────────────────
bind-address                    = 127.0.0.1
mysqlx-bind-address             = 127.0.0.1
EOF

sudo systemctl restart mysql
sudo mysql -e "SELECT @@innodb_buffer_pool_size/1024/1024 AS pool_mb, @@max_connections;"
```

> **`open_files_limit` aur `table_open_cache` per-tenant DBs ke liye ahem hain.** Har tenant DB ka apna `frm`/`ibd` set hai. 200 tenants × ~25 tables = 5000 files. Default `table_open_cache=4000` chhoti hai — `Table 'x' was not locked with LOCK TABLES` aur random "Too many open files" isi se aate hain.

> **`local_infile=0`** — SQL injection ka ek upgrade path (`LOAD DATA LOCAL INFILE` se server-side file read) band karta hai.

### 2.6 Redis

```bash
sudo apt -y install redis-server

sudo sed -i 's/^# maxmemory .*/maxmemory 768mb/'                  /etc/redis/redis.conf
sudo sed -i 's/^# maxmemory-policy .*/maxmemory-policy noeviction/' /etc/redis/redis.conf
sudo sed -i 's/^supervised no/supervised systemd/'                 /etc/redis/redis.conf
sudo sed -i 's/^bind 127.0.0.1 ::1/bind 127.0.0.1/'                /etc/redis/redis.conf
sudo sed -i 's/^# requirepass .*/requirepass CHANGE_ME_REDIS_STRONG/' /etc/redis/redis.conf
sudo sed -i 's/^appendonly no/appendonly yes/'                     /etc/redis/redis.conf

sudo systemctl enable --now redis-server
redis-cli -a CHANGE_ME_REDIS_STRONG ping
# → PONG
```

> **`maxmemory-policy noeviction` — `allkeys-lru` NAHI.** Yeh queue ke liye critical hai. `allkeys-lru` par Redis memory bharte hi **queue jobs evict** kar deta hai — chupchap, koi error nahi, job gayab. `noeviction` par Redis write refuse karta hai (`OOM command not allowed`) jo loud failure hai, silent data loss nahi. `06` §2.7.3 ke `afterCommit` jobs isi par depend karte hain.

> **`appendonly yes`** — Redis restart par queue aur session bach jate hain. `dump.rdb` snapshot (default) 5-min window kho sakta hai.

> **`requirepass` lazmi** — Redis par koi auth nahi hai by default, aur `bind 127.0.0.1` ke baghair woh internet par khula hota hai. Dono ek saath.

**Kyun Redis, `file`/`database` nahi:**

| Cheez | `file` | `database` | `redis` |
|---|---|---|---|
| `CacheTenancyBootstrapper` OFF hai (`07` §1.5 O2) → `Cache::tags()` **kabhi nahi** | ✅ | ✅ | ✅ |
| `SettingsStore::all()` multi-server consistency (**bug B-5**) | ❌ stale | ⚠️ per-node | ✅ |
| `withoutOverlapping()` lock | ✅ | ✅ | ✅ |
| `onOneServer()` lock | ❌ | ✅ | ✅ |
| Queue throughput | ❌ | ⚠️ polling | ✅ |
| **Tests `CACHE_STORE=array`** — array taggable hai, is liye `tags()` ghalti se aaye to **tests pass, prod 500** | — | — | — |

> **Bug B-5** (`01-STATUS-REPORT.md:223`): `SettingsStore::all()` `Cache::rememberForever` use karta hai. `database`/`file` store par doosre node ko stale value milti hai. **Production mein Redis lazmi.**

### 2.7 Supervisor

```bash
sudo apt -y install supervisor
sudo systemctl enable --now supervisor
supervisorctl version
```

### 2.8 PHP-FPM pools — ek pool per app

```bash
# ── CP: /etc/php/8.5/fpm/pool.d/kb.conf ────────────────────────────────────
sudo tee /etc/php/8.5/fpm/pool.d/kb.conf > /dev/null <<'EOF'
[kb]
user = deploy
group = www-data

listen = /run/php/php8.5-fpm-kb.sock
listen.owner = www-data
listen.group = www-data
listen.mode  = 0660

pm = dynamic
pm.max_children      = 24
pm.start_servers     = 6
pm.min_spare_servers = 4
pm.max_spare_servers = 12
pm.max_requests      = 500

; Provisioning queue worker 300s tak chalta hai, magar woh CLI hai (Supervisor),
; FPM nahi. FPM request 60s se zyada nahi honi chahiye.
request_terminate_timeout = 60s
request_slowlog_timeout   = 10s
slowlog = /var/log/php8.5-fpm-kb-slow.log

php_admin_value[memory_limit]        = 256M
php_admin_value[upload_max_filesize] = 12M
php_admin_value[post_max_size]       = 12M
php_admin_value[error_log]           = /var/log/php8.5-fpm-kb.log
php_admin_flag[log_errors]           = on
php_admin_flag[display_errors]       = off

; OPcache shared across pool ke workers
php_admin_value[opcache.memory_consumption] = 192
EOF

# ── Product: /etc/php/8.5/fpm/pool.d/mizaan.conf ────────────────────────────
sed 's/^\[kb\]$/[mizaan]/; s/fpm-kb/fpm-mizaan/g; s/fpm_kb/fpm_mizaan/g' \
  /etc/php/8.5/fpm/pool.d/kb.conf \
  | sed 's/pm.max_children      = 24/pm.max_children      = 20/' \
  | sed 's/opcache.memory_consumption] = 192/opcache.memory_consumption] = 160/' \
  | sudo tee /etc/php/8.5/fpm/pool.d/mizaan.conf > /dev/null

sudo php-fpm8.5 -t
sudo systemctl restart php8.5-fpm
ls -l /run/php/
# → php8.5-fpm-kb.sock, php8.5-fpm-mizaan.sock
```

**Sizing math (8 GB box):**

| Consumer | RAM |
|---|---|
| MySQL buffer pool | 4.0 GB |
| Redis `maxmemory` | 0.75 GB |
| OS + Nginx + Supervisor | 0.6 GB |
| FPM: 44 children × ~35 MB (realistic Laravel) | 1.5 GB |
| 8 queue workers × ~80 MB | 0.6 GB |
| **Total** | **~7.5 GB** |

> **`pm.max_children` ka formula:** `available_RAM / avg_process_size`. `avg_process_size` pata karne ke liye: `ps -o rss= -C php-fpm8.5 | awk '{s+=$1} END {print s/NR/1024" MB"}'`. Agar 45 MB aaye to `max_children` kam karein. **OOM killer FPM ko maar dega, MySQL ko nahi** (MySQL ka `oom_score_adj` zyada hota hai), aur woh outage hai.

> **`request_slowlog_timeout = 10s`** — 200 tenants par slow request ka source dhoondna mushkil hota hai. Slowlog exact stack trace deta hai.

---

## 3. MySQL — per-product credentials aur grants

`07` §10.2 ka model. **Yeh section us poore design ka deployment-side implementation hai.**

### 3.1 Kaun kahan kya banata hai

**Sab se pehle ek ghalat-fehmi door karein:** CP **kabhi** tenant DB nahi banata.

```text
CP (khattakbits.com)                    PRODUCT (mizaanbitserp.com)
────────────────────                    ───────────────────────────
tenants_registry (central)              mizaan_central        (central)
                                        mizaan_t_<ULID>       (per tenant, N)
subscriptions (central)
                                        tenants:provision  ← YAHAN chalta hai
POST /api/v1/provision  ───────────────►  tenant DB + migrate + seed
  (04 §5.3: payment verify ke baad)       owner mapping + OAuth client
```

Natija:

| Credential | Kis `.env` mein | Kya kar sakta hai |
|---|---|---|
| `kb_app` | **CP** | `khattakbits` DB par DML/DDL. Koi `CREATE DATABASE` nahi |
| `mizaan_app` | **Product** | `mizaan_central` + `mizaan_t_%` par DML/DDL. Koi `CREATE DATABASE` nahi |
| `mizaan_provision` | **Product (sirf CLI)** | `CREATE`/`DROP` database. Web process mein **load nahi** |
| `mizaan_backup` | **Backup script (root/deploy)** | `SELECT`, `LOCK TABLES`, `SHOW VIEW`, `TRIGGER`, `PROCESS` — koi write nahi |
| `lawbits_*` | Second product | `lawbits_t_%` — `mizaan` ki DBs ko **chhu hi nahi sakta** |

### 3.2 Grants — exact SQL

```bash
sudo mysql <<'SQL'
-- ═══════════════════════════════════════════════════════════════════════════
-- 1) CP — khattakbits.com
-- ═══════════════════════════════════════════════════════════════════════════
CREATE DATABASE IF NOT EXISTS `khattakbits`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'kb_app'@'127.0.0.1'
  IDENTIFIED WITH caching_sha2_password BY '<RANDOM_48_CHARS>';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      LOCK TABLES, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EXECUTE
  ON `khattakbits`.* TO 'kb_app'@'127.0.0.1';


-- ═══════════════════════════════════════════════════════════════════════════
-- 2) PRODUCT — mizaanbitserp.com
-- ═══════════════════════════════════════════════════════════════════════════
CREATE DATABASE IF NOT EXISTS `mizaan_central`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'mizaan_app'@'127.0.0.1'
  IDENTIFIED WITH caching_sha2_password BY '<RANDOM_48_CHARS>';

-- (a) Tenant DBs — `_` escape LAZMI (07 §10.2.2)
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      LOCK TABLES, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EXECUTE
  ON `mizaan\_t\_%`.* TO 'mizaan_app'@'127.0.0.1';

-- (b) Product central DB
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      LOCK TABLES, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EXECUTE
  ON `mizaan_central`.* TO 'mizaan_app'@'127.0.0.1';


-- ═══════════════════════════════════════════════════════════════════════════
-- 3) PROVISION — CREATE/DROP database, SIRF CLI
-- ═══════════════════════════════════════════════════════════════════════════
CREATE USER IF NOT EXISTS 'mizaan_provision'@'127.0.0.1'
  IDENTIFIED WITH caching_sha2_password BY '<RANDOM_64_CHARS>';

-- Option A (safe-in-practice): global CREATE/DROP, magar DML pattern tak mehdood
GRANT CREATE, DROP ON *.* TO 'mizaan_provision'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES,
      LOCK TABLES, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, TRIGGER, EXECUTE
  ON `mizaan\_t\_%`.* TO 'mizaan_provision'@'127.0.0.1';


-- ═══════════════════════════════════════════════════════════════════════════
-- 4) BACKUP — read-only, saari DBs
-- ═══════════════════════════════════════════════════════════════════════════
CREATE USER IF NOT EXISTS 'mizaan_backup'@'127.0.0.1'
  IDENTIFIED WITH caching_sha2_password BY '<RANDOM_48_CHARS>';

GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT, PROCESS
  ON *.* TO 'mizaan_backup'@'127.0.0.1';


FLUSH PRIVILEGES;
SQL
```

**`_` escape ka farq — yeh ghalat likhne se security hole ban jata hai** (`07` §10.2.2):

| Likha | MySQL ka matlab | Nateeja |
|---|---|---|
| `` `mizaan_t_%` `` | `mizaan` + **koi bhi ek char** + `t` + **koi bhi ek char** + kuch bhi | `mizaanXtY_anything` bhi match — **galat, privilege leak** |
| `` `mizaan\_t\_%` `` | literal `mizaan_t_` + kuch bhi | Sirf is product ki tenant DBs ✅ |

### 3.3 `GRANT CREATE ON \`mizaan\_t\_%\`.*` — kya yeh `CREATE DATABASE` deta hai?

**`NOT VERIFIED`.** Yeh is guide ka sab se ahem unverified item hai.

MySQL 8.0 ki documented behaviour: `CREATE DATABASE` ke liye **`CREATE` privilege global scope (`ON *.*`) par** chahiye. Database-scope `CREATE` sirf us database ke andar `CREATE TABLE` deta hai. `07` §10.2.3 ne yahi likha hai magar isay test nahi kiya.

**Production se pehle probe chalayein (5 minute ka kaam):**

```bash
# ── Ek scratch/staging MySQL par. Production par NAHI. ─────────────────────
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS `probe_central`;
CREATE USER IF NOT EXISTS 'probe_prov'@'127.0.0.1' IDENTIFIED BY 'ProbeTest123!';
GRANT CREATE, DROP ON `probe\_t\_%`.* TO 'probe_prov'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# ── provision user BAN ke try karein ───────────────────────────────────────
mysql -u probe_prov -p'ProbeTest123!' -h 127.0.0.1 -e "CREATE DATABASE \`probe_t_one\`;"
echo "exit=$?"

# SUCCESS (exit=0) → pattern grant kaafi hai. §3.2 ka `ON *.*` HATA DEIN.
# ERROR 1044 (Access denied ... to database 'probe_t_one')
#                  → global CREATE lazmi hai. Option A hi rahega.
```

**Result ke mutabiq:**

| Probe result | Kya karein | Blast radius |
|---|---|---|
| **`CREATE DATABASE` chalta hai** | `GRANT CREATE, DROP ON *.*` line **delete** karein. Provision user sirf pattern par kaam karega | **Behtareen** — provision credential leak ho bhi jaye to sirf `mizaan_t_*` par |
| **`ERROR 1044`** | Option A (global `CREATE`/`DROP`) rakhein — **magar** neeche ke chaar controls lazmi | Credential leak = **poora MySQL server** (`DROP DATABASE khattakbits`) |

**Option A ke chaar mitigating controls (agar majboori ho):**

1. **Credential web process mein load hi na ho.** `DB_PROVISION_USERNAME`/`DB_PROVISION_PASSWORD` sirf `database.connections.provision` mein jate hain, jo **default connection nahi**. `.env` se PHP-FPM ko yeh keys dikhani hi nahi chahiye — `pool.d/*.conf` mein `clear_env = yes` (default) hai, is liye FPM ke `.env` load se pehle hi... **nahi, Laravel khud `.env` parhta hai.** Is liye behtar tareeqa: provision keys ko **`.env.cli`** mein rakhein aur `config/database.php` mein `env('DB_PROVISION_USERNAME')` ki jagah `config('tenancy.provision.username')` use karein jo sirf CLI boot par load ho. **`NEW`** — yeh wiring abhi maujood nahi.
2. **`tenants:destroy` `--confirm={slug}` maange** (`07` §6.4, already designed) — typo se poora tenant nahi udta.
3. **Web app ka user `mizaan_app` hai, `mizaan_provision` nahi.** SQL injection web app mein ho to `mizaan_app` milta hai — jise `CREATE DATABASE` ka haq **nahi**.
4. **`security_events` mein `tenant.db_created`/`tenant.db_dropped`** audit rows (actor = admin).

> **Yeh probe skip na karein.** Agar aap Option A assume kar ke chale aur probe kehta hai pattern kaafi tha, to aap ne bekaar mein poore server ka `DROP DATABASE` privilege ek credential ko de diya. Ulta bhi sach hai: agar aap pattern assume karein aur `ERROR 1044` aaye, to **pehla tenant provision hi fail hoga** — production mein, customer ke saamne.

### 3.4 Backup user — kya kyun

| Privilege | Kyun chahiye |
|---|---|
| `SELECT` | `mysqldump` har table parhna |
| `LOCK TABLES` | `--single-transaction` ke baghair consistent dump |
| `SHOW VIEW` | Views ka definition dump |
| `TRIGGER` | Triggers dump |
| `EVENT` | `event_scheduler` entries dump |
| `PROCESS` | `--single-transaction` ko `FLUSH TABLES WITH READ LOCK` ki zaroorat nahi, magar `SHOW ENGINE INNODB STATUS` aur `--master-data` ke liye |

**`RELOAD`, `SUPER`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `FILE` — koi nahi.** Backup user leak ho jaye to data **parh** sakte hain (jo dump mein bhi hai) magar **likh ya delete nahi** sakte. `FILE` privilege especially khatarnak hai — us se server par files likhi ja sakti hain.

### 3.5 Grants verify karein

```bash
sudo mysql <<'SQL'
SELECT user, host, plugin FROM mysql.user WHERE user LIKE 'kb\_%' OR user LIKE 'mizaan\_%';
SQL

# ── Har user ke grants alag se dekhein ─────────────────────────────────────
for U in kb_app mizaan_app mizaan_provision mizaan_backup; do
  echo "═══ $U ═══"
  sudo mysql -N -B -e "SHOW GRANTS FOR '$U'@'127.0.0.1';"
done
```

**Expected — `mizaan_app` par `*.*` ki ek bhi line nahi honi chahiye:**

```text
GRANT USAGE ON *.* TO `mizaan_app`@`127.0.0.1`                  ← sirf yeh normal hai
GRANT ... ON `mizaan\_t\_%`.* TO `mizaan_app`@`127.0.0.1`
GRANT ... ON `mizaan_central`.* TO `mizaan_app`@`127.0.0.1`
```

```bash
# ── Negative test: mizaan_app ko doosri product ki DB nahi dikhni chahiye ──
mysql -u mizaan_app -p -h 127.0.0.1 -e "SELECT COUNT(*) FROM lawbits_central.users;" 2>&1
# → ERROR 1142 (42000): SELECT command denied ...  ✅
```

> **`GRANT USAGE ON *.*` line normal hai** — woh "koi privilege nahi" ka placeholder hai jo har user ke paas hota hai. Ghabrayein nahi.

### 3.6 `config/database.php` — teen connections

`07` §2.4 ka connection pinning matrix. Yeh **silent data corruption** ka sab se bara source hai, is liye exact config:

```php
// config/database.php  (NEW wiring — aaj sirf `mysql` + `sqlite` hain)
'default' => env('DB_CONNECTION', 'central'),   // ⚠️ 'mysql' NAHI, 'central'

'connections' => [

    // ── CENTRAL — CP ka apna DB, aur product ka product-central DB ──────────
    'central' => [
        'driver'         => 'mysql',
        'host'           => env('DB_HOST', '127.0.0.1'),
        'port'           => env('DB_PORT', '3306'),
        'database'       => env('DB_DATABASE'),
        'username'       => env('DB_USERNAME'),
        'password'       => env('DB_PASSWORD'),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'prefix'         => '',
        'strict'         => true,
        'engine'         => 'InnoDB',
    ],

    // ── PROVISION — sirf CLI. Default NAHI, web se reachable NAHI ───────────
    'provision' => [
        'driver'         => 'mysql',
        'host'           => env('DB_HOST', '127.0.0.1'),
        'port'           => env('DB_PORT', '3306'),
        'database'       => null,                        // CREATE DATABASE ke liye
        'username'       => env('DB_PROVISION_USERNAME'),
        'password'       => env('DB_PROVISION_PASSWORD'),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'strict'         => true,
    ],

    // ── TENANT — bootstrapper isay runtime par switch karta hai ─────────────
    'tenant' => [
        'driver'         => 'mysql',
        'host'           => env('DB_HOST', '127.0.0.1'),
        'port'           => env('DB_PORT', '3306'),
        'database'       => null,                        // runtime par set hota hai
        'username'       => env('DB_USERNAME'),
        'password'       => env('DB_PASSWORD'),
        'charset'        => 'utf8mb4',
        'collation'      => 'utf8mb4_unicode_ci',
        'prefix'         => '',
        'strict'         => true,
    ],
],
```

**`'default' => 'central'` kyun, `'mysql'` nahi** (`07` §2.4):

`DatabaseTenancyBootstrapper` `database.default` ko `tenant` par switch karta hai. Agar default `mysql` ho to switch ke baad `mysql` **tenant** ban jata hai — aur jo bhi subsystem tenancy init ke **baad** apna connection resolve karega, woh tenant DB pakdega. `central` alias hone se switch reversible aur explicit hai.

**Teen pinning lazmi (`07` §2.4)** — in ke baghair `sessions`, jobs aur cache tenant DB mein chale jayenge:

```php
// config/session.php
'connection' => env('SESSION_CONNECTION', 'central'),   // 'null' NAHI

// config/queue.php
'connections' => [
    'database' => [
        'connection' => env('DB_QUEUE_CONNECTION', 'central'),   // 'null' NAHI
        // ...
    ],
],

// config/cache.php
'stores' => [
    'database' => [
        'connection' => env('DB_CACHE_CONNECTION', 'central'),   // 'null' NAHI
    ],
],
```

> **`sessions` table tenant DB mein maujood hi nahi hoti.** Woh `create_users_table.php` mein banti hai (host migration), aur tenant DBs sirf `database/migrations/tenant/` chalti hain. Agar `session.connection` `null` rahe aur koi resolve-order change session ko tenant DB par le jaye, to **har request par `Table 'mizaan_t_xxx.sessions' doesn't exist`** — aur error sirf tab aata hai jab koi page render ho, is liye staging par bhi pakadna mushkil hai.

---

## 4. Nginx

### 4.1 Snippets

```bash
sudo apt -y install nginx
sudo rm -f /etc/nginx/sites-enabled/default
sudo mkdir -p /etc/nginx/snippets
```

```bash
# ── /etc/nginx/snippets/kb-security.conf ───────────────────────────────────
sudo tee /etc/nginx/snippets/kb-security.conf > /dev/null <<'EOF'
# ═══════════════════════════════════════════════════════════════════════════
# Yahan CSP/HSTS/X-Frame-Options NA likhein.
#
# App khud bhejta hai — khattakbits-core/src/Http/Middleware/SecurityHeaders.php
#   X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
#   Strict-Transport-Security (max-age=31536000, sirf HTTPS par)
#   Content-Security-Policy / -Report-Only (config/security.php, CSP_MODE)
#
# Duplicate CSP header = browser DONO ka INTERSECTION enforce karta hai.
# Debug karna almost impossible ho jata hai (jo cheez app mein allowed hai
# woh Nginx ki purani copy se block ho jati hai).
# ═══════════════════════════════════════════════════════════════════════════

server_tokens off;
EOF

# ── /etc/nginx/snippets/kb-laravel.conf ────────────────────────────────────
sudo tee /etc/nginx/snippets/kb-laravel.conf > /dev/null <<'EOF'
charset utf-8;
client_max_body_size 12M;      # PHP upload_max_filesize ke barabar

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location = /favicon.ico { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }

# .env, .git, .htaccess — sab block
location ~ /\.(?!well-known).* { deny all; }

# Vite build assets — immutable (hashed filenames)
location ~* ^/build/.*\.(js|css|woff2?|ttf|eot|png|jpe?g|svg|glb|gltf|webp)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
EOF

# ── /etc/nginx/snippets/kb-php-kb.conf (CP ka FPM socket) ──────────────────
sudo tee /etc/nginx/snippets/kb-php-kb.conf > /dev/null <<'EOF'
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;

    fastcgi_pass unix:/run/php/php8.5-fpm-kb.sock;
    fastcgi_index index.php;

    include fastcgi_params;

    # $realpath_root — symlink resolve karta hai. Yeh OPcache
    # validate_timestamps=0 ke saath LAZMI hai: naya release = naya real
    # path = naya opcache entry. $document_root se purana code serve hota hai.
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT   $realpath_root;

    fastcgi_hide_header X-Powered-By;
    fastcgi_read_timeout 60s;
    fastcgi_buffers 16 16k;
    fastcgi_buffer_size 32k;
}
EOF

# ── /etc/nginx/snippets/kb-php-mizaan.conf — sirf socket ka naam badalta hai
sed 's/php8.5-fpm-kb/php8.5-fpm-mizaan/g' /etc/nginx/snippets/kb-php-kb.conf \
  | sudo tee /etc/nginx/snippets/kb-php-mizaan.conf > /dev/null
```

**`$realpath_root` verify (yeh zero-downtime ka load-bearing part hai):**

```bash
# Ubuntu ke /etc/nginx/fastcgi_params mein HTTPS param hota hai — verify karein
grep -n "HTTPS\|REQUEST_SCHEME" /etc/nginx/fastcgi_params
# → fastcgi_param  REQUEST_SCHEME     $scheme;
# → fastcgi_param  HTTPS              $https if_not_empty;
```

> Agar `HTTPS` param **na** ho to `$request->isSecure()` **false** rahega — HSTS header kabhi nahi jayega, `secure` cookie flags par bhi asar, aur `URL::forceScheme` ke baghair generated URLs `http://` honge. Missing ho to `kb-php-*.conf` mein add karein:
> ```nginx
> fastcgi_param HTTPS $https if_not_empty;
> fastcgi_param REQUEST_SCHEME $scheme;
> ```

### 4.2 Rate limit zones (`05` §3.8)

```bash
# ── /etc/nginx/snippets/kb-ratelimit.conf ──────────────────────────────────
sudo tee /etc/nginx/snippets/kb-ratelimit.conf > /dev/null <<'EOF'
# 05 §3.8: POST /login 5/min app-level hai; Nginx ek coarse outer layer hai
limit_req_zone $binary_remote_addr zone=kb_login:10m  rate=10r/m;
limit_req_zone $binary_remote_addr zone=kb_oauth:10m  rate=60r/m;
limit_req_zone $binary_remote_addr zone=kb_api:10m    rate=240r/m;
limit_req_status 429;
limit_req_log_level warn;
EOF
```

`/etc/nginx/nginx.conf` ke `http { }` block mein (line `include /etc/nginx/mime.types;` ke baad):

```nginx
include /etc/nginx/snippets/kb-ratelimit.conf;
```

> **Per-IP limits `05` §3.8 ki per-`client_id` limits se kam hain?** Nahi — Nginx layer **IP** par hai (coarse, bot protection), app layer `client_id`/`sub` par (precise, `05` §3.8 ki table). Dono ek saath chalte hain. `resolve` ka 600/min app limit Nginx ke 240/min se zyada hai, magar `resolve` per-product 5-min cache ke saath call hota hai (`config/kb.php` `resolve.ttl => 300`) — ek product ka worker 240/min tak nahi pahunchta.

### 4.3 Control plane — `khattakbits.com`

```bash
sudo tee /etc/nginx/sites-available/khattakbits.conf > /dev/null <<'EOF'
# ── Port 80: sirf ACME + redirect ──────────────────────────────────────────
server {
    listen 80;
    listen [::]:80;
    server_name khattakbits.com www.khattakbits.com;

    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://khattakbits.com$request_uri; }
}

# ── Port 443 ───────────────────────────────────────────────────────────────
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;                                    # Ubuntu 22.04: yeh line hata kar
                                                 # `listen 443 ssl http2;` karein
    server_name khattakbits.com;

    root  /var/www/khattakbits/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/khattakbits.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/khattakbits.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL_kb:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    ssl_stapling        on;
    ssl_stapling_verify on;
    resolver            127.0.0.53 valid=300s;   # systemd-resolved (Ubuntu 24.04)
    resolver_timeout    5s;

    access_log /var/log/nginx/khattakbits.access.log;
    error_log  /var/log/nginx/khattakbits.error.log warn;

    include /etc/nginx/snippets/kb-security.conf;
    include /etc/nginx/snippets/kb-laravel.conf;

    # ── Login: 3D login + Passport. Nginx coarse throttle ──────────────────
    location = /login {
        limit_req zone=kb_login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── OAuth2 + API (05 §3.8) ─────────────────────────────────────────────
    location ~ ^/oauth/ {
        limit_req zone=kb_oauth burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ ^/api/ {
        limit_req zone=kb_api burst=60 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── Health check — rate limit se exempt, access log off ────────────────
    location = /up {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    include /etc/nginx/snippets/kb-php-kb.conf;
}

# ── www → apex (host-only cookie ke liye LAZMI, §7.3) ─────────────────────
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name www.khattakbits.com;

    ssl_certificate     /etc/letsencrypt/live/khattakbits.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/khattakbits.com/privkey.pem;

    return 301 https://khattakbits.com$request_uri;
}
EOF

sudo ln -sfn /etc/nginx/sites-available/khattakbits.conf /etc/nginx/sites-enabled/khattakbits.conf
```

> **`www` → apex redirect kyun lazmi hai:** `SESSION_DOMAIN` khali (host-only) hai (§7.3). Agar `www.khattakbits.com` par bhi app serve kare, to `khattakbits.com` par set cookie `www` ko nahi milegi → har navigation par logout. Redirect se ek hi host bachta hai.

### 4.4 Product app — `mizaanbitserp.com` (**path-based, koi wildcard block nahi**)

```bash
sudo tee /etc/nginx/sites-available/mizaanbits.conf > /dev/null <<'EOF'
# ═══════════════════════════════════════════════════════════════════════════
# PATH-BASED TENANCY (07 §3.1)
#
#   https://mizaanbitserp.com/acme/dashboard      ← tenant "acme"
#   https://mizaanbitserp.com/beta/invoices       ← tenant "beta"
#   https://mizaanbitserp.com/login               ← central route
#
# Wildcard server block KI ZAROORAT NAHI HAI. `{tenant}` ek PATH segment hai,
# hostname nahi. Laravel `InitializeTenancyByPath` isay resolve karta hai
# (07 §3.2 step 7).
#
# Isi liye: koi `*.mizaanbitserp.com` DNS record nahi, koi wildcard cert
# nahi, koi DNS-01 challenge nahi, koi Cloudflare API token nahi.
# ═══════════════════════════════════════════════════════════════════════════

server {
    listen 80;
    listen [::]:80;
    server_name mizaanbitserp.com www.mizaanbitserp.com;

    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
    location / { return 301 https://mizaanbitserp.com$request_uri; }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;

    server_name mizaanbitserp.com;

    root  /var/www/mizaanbits/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/mizaanbitserp.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mizaanbitserp.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_session_cache   shared:SSL_mizaan:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    ssl_stapling        on;
    ssl_stapling_verify on;
    resolver            127.0.0.53 valid=300s;
    resolver_timeout    5s;

    access_log /var/log/nginx/mizaan.access.log;
    error_log  /var/log/nginx/mizaan.error.log warn;

    include /etc/nginx/snippets/kb-security.conf;
    include /etc/nginx/snippets/kb-laravel.conf;

    # ── SSO callback + login (central routes, tenancy nahi) ────────────────
    location = /login {
        limit_req zone=kb_login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── Webhook receiver — HMAC se protected hai, rate limit se exempt ─────
    #    (05 §4.3; CP ke retries ko 429 nahi milna chahiye)
    location = /webhooks/kb {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # ── Billing (renew / expired) — CP se aane wale redirects ──────────────
    location ~ ^/billing/ {
        limit_req zone=kb_api burst=30 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /up {
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    include /etc/nginx/snippets/kb-php-mizaan.conf;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name www.mizaanbitserp.com;

    ssl_certificate     /etc/letsencrypt/live/mizaanbitserp.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mizaanbitserp.com/privkey.pem;

    return 301 https://mizaanbitserp.com$request_uri;
}
EOF

sudo ln -sfn /etc/nginx/sites-available/mizaanbits.conf /etc/nginx/sites-enabled/mizaanbits.conf
sudo nginx -t && sudo systemctl reload nginx
```

**Purane `02` guide se teen cheezein khatam ho gayin:**

| Purana `02` §5.3 | Ab | Kyun |
|---|---|---|
| `server_name ~^(?<tenant>[a-z0-9-]+)\.mizaanbitserp\.com$;` regex block | ❌ **Khatam** | `{tenant}` path segment hai. Nginx ko tenant ka pata hi nahi hona chahiye |
| `fastcgi_param TENANT_SLUG $tenant;` | ❌ **Khatam** | Slug `{tenant}` route param se aata hai (`07` §3.1). Nginx se inject karna **khatarnak** tha — app us header/param par bharosa kar leti to host-based resolution wapas aa jata |
| Alag access/error log per tenant block | ❌ **Khatam** | Ek log file. Tenant ka pata `RequestId` + Laravel log context se chalta hai |

> **Agar koi kehta hai "wildcard block chahiye"** — woh pre-14-Nov design hai. Path-based mein ek hi host hai. `04` §5.3, `05` §1.2, `07` §3.1 sab path-based hain, aur `05` §1.2 mein koi `subdomain` field hi nahi hai.

### 4.5 Baaki domains — `bitcart.com`, `lawbits.com`

```bash
for D in bitcart lawbits; do
  sudo sed "s/mizaanbitserp/${D}.com/g; s/mizaanbits/${D}/g; s/fpm-mizaan/fpm-${D}/g; s/SSL_mizaan/SSL_${D}/g" \
    /etc/nginx/sites-available/mizaanbits.conf \
    | sudo tee /etc/nginx/sites-available/${D}.conf > /dev/null
  sudo ln -sfn /etc/nginx/sites-available/${D}.conf /etc/nginx/sites-enabled/${D}.conf
done
sudo nginx -t && sudo systemctl reload nginx
```

**Har naye product ke liye 7 cheezein (checklist — bhoolna aasan hai):**

1. DNS A record (apex + `www`) → server IP
2. `/var/www/<app>/{releases,shared/storage}` directory (§8.1)
3. FPM pool `pool.d/<app>.conf` (§2.8) + `kb-php-<app>.conf` snippet
4. Nginx server block (§4.4 copy) + `nginx -t`
5. `certbot certonly` (§5.1)
6. MySQL: `CREATE DATABASE <app>_central`, user `<app>_app`, grants `ON \`<app>\_t\_%\`.*` (§3.2)
7. `.env` (§7.2) + `TENANCY_DB_PREFIX=<app>_t_`

### 4.6 Custom domains — **Phase 2**

`erp.acme.com` → MizaanbitsERP. Path-based tenancy iske saath **compatible hai** (`/{tenant}/...` prefix us domain par bhi chalega), magar do naye masle aate hain jo Phase 1 mein nahi hain:

| Masla | Kyun mushkil |
|---|---|
| **TLS per domain** | 500 customers = 500 certs. HTTP-01 chalega magar DNS propagation customer ke control mein hai — support burden |
| **Tenant resolution** | Host-based lookup chahiye **pehle**, phir path. Yani `07` §3.1 ka route structure badalna padega (ya dual-mode) |
| **Cookie** | Har custom domain ka apna cookie — session model `07` §3.7 badalta hai |

**Do options (dono Phase 2):**

**Option A — certbot per domain (manual, safe):**
```bash
# Har custom domain par
sudo certbot certonly --nginx -d erp.acme.com \
  --email admin@khattakbits.com --agree-tos --no-eff-email
# Phir ek server block + `server_name erp.acme.com;`
```
500 domains par yeh **kaam nahi karega** — Nginx config reload 30s+ lega aur certbot rate limit (50 certs/domain/week, 5 duplicate/week) hit hoga.

**Option B — Caddy on-demand TLS (optional, recommended Phase 2):**
```caddyfile
# Caddyfile — Nginx ki jagah, sirf custom domains ke liye
{
    on_demand_tls {
        ask http://127.0.0.1:8080/internal/tls-ask   # Laravel endpoint
    }
}

https:// {
    tls {
        on_demand
    }
    reverse_proxy 127.0.0.1:8080
}
```

Laravel side (`NEW`):
```php
// routes/web.php — sirf localhost se reachable
Route::get('/internal/tls-ask', function (Request $request) {
    abort_unless($request->ip() === '127.0.0.1', 403);

    $domain = $request->query('domain');

    return CustomDomain::query()
        ->where('domain', $domain)
        ->where('status', 'verified')
        ->exists()
        ? response('OK', 200)
        : response('Not allowed', 404);
})->withoutMiddleware(['web']);
```

> **`ask` endpoint ke baghair Caddy on-demand TLS ek open cert-issuance proxy hai** — koi bhi `evil.com` ko apna DNS point kar ke aap ke server se cert mangwa sakta hai, aur aap ka IP Let's Encrypt rate limit kha jayega. `ask` endpoint **lazmi** hai, optional nahi.

---

## 5. TLS — plain certbot, ek cert per domain

### 5.1 HTTP-01, per domain (path-based ka faida)

Wildcard cert **sirf DNS-01** se milta hai. Path-based tenancy mein **wildcard ki zaroorat hi nahi** — ek host, ek cert. Is liye plain HTTP-01, koi DNS API token nahi, koi Cloudflare credential nahi.

```bash
sudo apt -y install certbot python3-certbot-nginx
sudo mkdir -p /var/www/letsencrypt
sudo chown www-data:www-data /var/www/letsencrypt

# ── Har domain alag se (webroot method — Nginx config se independent) ──────
for D in khattakbits.com mizaanbitserp.com bitcart.com lawbits.com; do
  sudo certbot certonly --webroot -w /var/www/letsencrypt \
    -d "$D" -d "www.$D" \
    --email admin@khattakbits.com --agree-tos --no-eff-email \
    --non-interactive
done

sudo certbot certificates
```

**`--webroot` kyun, `--nginx` nahi:** `--nginx` plugin aap ki Nginx config **khud badalta hai** (443 block inject karta hai, `listen` lines modify karta hai). Deployer/Ansible se manage ho rahi config par woh unpredictable hai. `--webroot` sirf ek file likhta hai aur aap ki config ko chhuta nahi.

> **`www` subdomain ka cert bhi isi cert mein hai** (`-d "$D" -d "www.$D"`) — kyunke §4.3/§4.4 ka `www → apex` redirect block bhi `listen 443 ssl` karta hai, is liye usay bhi valid cert chahiye.

### 5.2 HSTS — `includeSubDomains` **kabhi nahi**

App khud HSTS bhejta hai: `Strict-Transport-Security: max-age=31536000` (`SecurityHeaders.php:26`), **`includeSubDomains` ke baghair**. Yeh sahi hai.

> **`includeSubDomains` ya `preload` kabhi na lagayein** jab tak saare subdomains HTTPS-ready na hon. `preload` list mein domain dalne ke baad **rollback almost impossible** hai — browsers mein baked-in hota hai, aur `http://anything.mizaanbitserp.com` permanently blocked ho jata hai. Phase 2 mein custom domains aayenge — unke certs banne se pehle `includeSubDomains` lagana unhe tod dega.

Verify:

```bash
curl -sI https://khattakbits.com | grep -i strict-transport
# → strict-transport-security: max-age=31536000
# `includeSubDomains` NAHI hona chahiye
```

> **Agar yeh header missing hai** → `$request->isSecure()` false hai → `fastcgi_param HTTPS` missing (§4.1) ya TLS Nginx par terminate ho raha hai magar PHP ko pata nahi. `TrustProxies` bhi check karein agar koi load balancer aage ho.

### 5.3 Renewal + reload hook

```bash
sudo mkdir -p /etc/letsencrypt/renewal-hooks/deploy
sudo tee /etc/letsencrypt/renewal-hooks/deploy/kb-reload.sh > /dev/null <<'EOF'
#!/bin/sh
set -e
systemctl reload nginx
# FPM reload NAHI — cert renewal ka code se koi taalluq nahi, aur FPM reload
# har in-flight request ko 50-200ms ke liye rok deta hai. Deploy hook (§11.1)
# yeh kaam karta hai.
logger -t certbot-kb "nginx reloaded after cert renewal"
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/kb-reload.sh

sudo systemctl enable --now certbot.timer
sudo systemctl list-timers certbot.timer
sudo certbot renew --dry-run
```

> **`NOT VERIFIED`:** certbot `--dry-run` staging cert banata hai aur hook **chala deta hai** (nginx reload). Production par pehli dafa chalane se pehle `journalctl -t certbot-kb -n 5` se confirm karein ke hook chala aur Nginx reload hua.

**Expiry monitoring** (§13.3): certbot timer 30 din pehle renew karta hai. Us se pehle alert chahiye:

```bash
# Har din, 21 din se kam expiry par alert
echo | openssl s_client -servername khattakbits.com -connect khattakbits.com:443 2>/dev/null \
  | openssl x509 -noout -checkend 1814400 || echo "CERT EXPIRING < 21d: khattakbits.com"
```

### 5.4 Custom domains (Phase 2)

§4.6 dekhein. Phase 1 mein **koi custom domain nahi**, is liye `certbot` ka wildcard ya `--expand` wala koi setup nahi.

---

## 6. Private Composer packages

### 6.1 Masla — `type: path` CI mein kaam nahi karta

`composer.json` aaj yeh hai (**VERIFIED**):

```json
"repositories": [
  {
    "type": "path",
    "url": "../khattakbits-core",
    "options": { "symlink": true }
  }
],
"require": {
  "khattakbits/core": "dev-main"
}
```

**GitHub Actions par yeh har dafa fail hota hai:**

```text
- Installing khattakbits/core (dev-main)
  Failed to download khattakbits/core from source: ...
  The "../khattakbits-core" directory does not exist
```

Wajah: `path` repository **local filesystem** ko point karta hai. Runner par `../khattakbits-core` maujood nahi (checkout sirf ek repo karta hai). Aur `"symlink": true` ka matlab `vendor/khattakbits/core` ek **symlink** hai jo nonexistent directory ko point karta hai — `composer install` "successful" dikh sakta hai magar `artisan` boot par `Class not found`.

**Fix: `type: vcs` + GitHub deploy key / `auth.json`.** Yahi fix server par bhi chahiye.

### 6.2 Teen private packages

| Package | Repo | Type | Kis app ko chahiye |
|---|---|---|---|
| `khattakbits/core` | `khattakbits/core` | composer | **CP + saare products** |
| `khattakbits/sso-client` | `khattakbits/sso-client` | composer | **Sirf products** (CP khud provider hai) |
| `@khattakbits/ui` | `khattakbits/ui` | **npm** (GitHub Packages ya `git+ssh`) | CP + products (Vue components) |

**`composer.json` — naya `repositories` block:**

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:khattakbits/core.git"
    },
    {
      "type": "vcs",
      "url": "git@github.com:khattakbits/sso-client.git"
    }
  ],
  "require": {
    "khattakbits/core": "^1.5",
    "khattakbits/sso-client": "^1.0"
  },
  "config": {
    "preferred-install": "dist",
    "sort-packages": true
  }
}
```

> **Local development ke liye `path` repo kaise rakhhein:** `composer.json` mein `vcs` rakhein (committed), aur local override ke liye **`COMPOSER` env ya `composer config repositories.local path ../khattakbits-core`** — yeh **`composer.json` ko modify karta hai**, is liye commit na karein. Behtar: `~/.composer/config.json` (global) mein path repo add karein — woh committed file ko chhuta nahi:
> ```bash
> composer config --global repositories.kb-core path /e/LaraProjects/khattakbits-core
> ```
> **Magar** global path repo CI par nahi hota — jo exactly wohi behaviour hai jo chahiye.

### 6.3 Server par: deploy key + `auth.json`

**Step 1 — deploy key (read-only):**

```bash
sudo -u deploy ssh-keygen -t ed25519 -C "kb-prod-01-composer" -f /home/deploy/.ssh/kb_composer -N ""

cat /home/deploy/.ssh/kb_composer.pub
# → ssh-ed25519 AAAA... kb-prod-01-composer
```

Yeh public key **dono** repos par add karein:
`github.com/khattakbits/core` → Settings → Deploy keys → Add deploy key → **Allow write access: OFF**
`github.com/khattakbits/sso-client` → same

```bash
# SSH config — kaunsa key kis host ke liye
sudo -u deploy tee /home/deploy/.ssh/config > /dev/null <<'EOF'
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/kb_composer
    IdentitiesOnly yes
    StrictHostKeyChecking accept-new

Host github.com-khattakbits
    HostName github.com
    User git
    IdentityFile ~/.ssh/kb_composer
    IdentitiesOnly yes
    StrictHostKeyChecking accept-new
EOF
sudo chmod 600 /home/deploy/.ssh/config

# ── VERIFY ────────────────────────────────────────────────────────────────
sudo -u deploy ssh -T git@github.com 2>&1 | head -2
# → Hi khattakbits/core! You've successfully authenticated...
```

**Step 2 — `auth.json` (server par, repo ke bahar):**

```bash
sudo -u deploy mkdir -p /home/deploy/.composer
sudo -u deploy tee /home/deploy/.composer/auth.json > /dev/null <<'EOF'
{
  "github-oauth": {
    "github.com": "ghp_READ_ONLY_PERSONAL_ACCESS_TOKEN"
  }
}
EOF
sudo chmod 600 /home/deploy/.composer/auth.json
sudo chown deploy:deploy /home/deploy/.composer/auth.json
```

> **Deploy key vs `auth.json` — dono chahiye?** Deploy key `git@` URLs ke liye kaafi hai **agar** `composer.json` mein `url` `git@github.com:...` ho. `auth.json` tab chahiye jab `https://` URLs use ho rahe hon ya GitHub API rate limit hit ho. **Behtareen: dono** — deploy key clone ke liye, `auth.json` metadata/API ke liye. Dono **read-only** scope par.

**Step 3 — `@khattakbits/ui` (npm):**

```bash
# .npmrc — server par /var/www/<app>/shared/.npmrc, repo ke bahar
sudo -u deploy tee /home/deploy/.npmrc > /dev/null <<'EOF'
@khattakbits:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${KB_NPM_TOKEN}
always-auth=true
EOF
sudo chmod 600 /home/deploy/.npmrc
```

`KB_NPM_TOKEN` = GitHub PAT with `read:packages`. Build CI mein hota hai (§11.4) is liye server par sirf fallback ke liye.

### 6.4 `package:discover` trap

`composer install` ke baad `post-autoload-dump` script `artisan package:discover` chalati hai. Agar `khattakbits/core` ka symlink toota hua ho ya package load na ho:

```text
In ProviderRepository.php line 208:
  Class "KhattakBits\Core\CoreServiceProvider" not found
```

**Diagnose:**

```bash
cd /var/www/khattakbits/current

# 1. Package vendor mein hai?
ls -la vendor/khattakbits/
# → core -> ../../../../khattakbits-core    ← SYMLINK = path repo = CI mein tootega
# → core                                    ← directory = vcs repo ✅

# 2. Autoload mein registered hai?
composer dump-autoload -o
php artisan about --only=environment

# 3. Config cache purana hai?
php artisan config:clear && php artisan optimize:clear
```

> **`ls -la vendor/khattakbits/` ki pehli line sab se ahem hai.** Symlink = `path` repository. Server/CI par yeh **kabhi** kaam nahi karega. `vcs` par shift karne ke baad woh real directory hogi.

### 6.5 Version pinning — `dev-main` se `^1.5` par jayein

Aaj `"khattakbits/core": "dev-main"` hai (**VERIFIED**). Production ke liye:

| `dev-main` | `^1.5` |
|---|---|
| Har deploy par latest `main` — jo bhi us waqt ho | `composer.lock` pin karta hai |
| Rollback purana code layega magar **naya package** — silent breakage | Rollback poora consistent |
| `composer.lock` ka koi matlab nahi | `composer.lock` authoritative |

```bash
# Core package par tag lagayein
cd /e/LaraProjects/khattakbits-core
git tag -a v1.5.0 -m "Path-based tenancy + blocked_reason + token_version"
git push origin v1.5.0

# Host app mein
cd /e/LaraProjects/saas-starter
composer require "khattakbits/core:^1.5"
composer update khattakbits/core --with-dependencies
git add composer.json composer.lock && git commit -m "chore(deps): pin khattakbits/core ^1.5"
```

> **`05` §0.2.1 ka wohi rule Passport par:** `"laravel/passport": "13.10.*"` — **caret nahi**. `^13.10` `13.11.x` allow karta hai jo `phpseclib 4.0.1` ko exact-pin karta hai, jo advisory `PKSA-h7cz-q7zq-mdkt` se blocked hai. Aur agar koi `policy.advisories.block=false` kar de to **vulnerable phpseclib chupchap install** ho jata hai. Yeh silent regression hai.

---

## 7. `.env` templates

### 7.1 Control plane — `/var/www/khattakbits/shared/.env`

```bash
sudo -u deploy tee /var/www/khattakbits/shared/.env > /dev/null <<'EOF'
# ═══════════════════════════════════════════════════════════════════════════
# KhattakBits Control Plane
# ═══════════════════════════════════════════════════════════════════════════
APP_NAME="KhattakBits"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_PREVIOUS_KEYS=
APP_URL=https://khattakbits.com
APP_TIMEZONE=Asia/Karachi
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

# ── Logging ────────────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30
LOG_DEPRECATIONS_CHANNEL=null

# ── Database (CP central — 09 §3.1) ────────────────────────────────────────
DB_CONNECTION=central
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=khattakbits
DB_USERNAME=kb_app
DB_PASSWORD=CHANGE_ME_48_CHARS

# CP kabhi tenant DB nahi banata (09 §3.1) — provision credential YAHAN NAHI.
# DB_PROVISION_USERNAME / DB_PROVISION_PASSWORD sirf product app ke .env mein.

# ── Session (host-only cookie — 09 §7.3) ───────────────────────────────────
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=kb_session
SESSION_CONNECTION=central

# ── Cache / Queue / Redis ──────────────────────────────────────────────────
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=CHANGE_ME_REDIS_STRONG
REDIS_PORT=6379
REDIS_PREFIX=kb_
DB_CACHE_CONNECTION=central
DB_QUEUE_CONNECTION=central

# ── Filesystem ─────────────────────────────────────────────────────────────
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=local

# ── Mail ───────────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS="no-reply@khattakbits.com"
MAIL_FROM_NAME="${APP_NAME}"

# ── Security (SecurityHeaders middleware) ──────────────────────────────────
# report-only → 1 hafta → enforce (09 §14.1)
CSP_MODE=report-only
ALLOWED_SSO_HOSTS="mizaanbitserp.com,bitcart.com,lawbits.com"

# ── Passport / OAuth2 (05 §0.2.1) ──────────────────────────────────────────
# composer.json: "laravel/passport": "13.10.*"  ← CARET NAHI
PASSPORT_PRIVATE_KEY=
PASSPORT_PUBLIC_KEY=
OAUTH_ACCESS_TOKEN_TTL=15
OAUTH_REFRESH_TOKEN_TTL=30
OAUTH_GRACE_PERIOD_DAYS=7

# ── Webhooks (outbound to products, 05 §4.2) ───────────────────────────────
KB_WEBHOOK_MAX_ATTEMPTS=7
KB_WEBHOOK_TIMEOUT=10
KB_WEBHOOK_TOLERANCE=300

# ── Payments (06 §5.2.2 entry 1) ───────────────────────────────────────────
PAYMENTS_PHASE=1
# Gateway credentials DB mein `encrypted:array` hain (06 §5.2) — .env mein NAHI

# ── Ops ────────────────────────────────────────────────────────────────────
KB_OPS_EMAIL=ops@khattakbits.com

# ── Attachments ────────────────────────────────────────────────────────────
ATTACHMENT_MAX_KB=10240
DOCUMENT_VIEW_DEDUPE_MINUTES=10

# ── Vite ───────────────────────────────────────────────────────────────────
VITE_APP_NAME="${APP_NAME}"
EOF

sudo chown deploy:www-data /var/www/khattakbits/shared/.env
sudo chmod 640 /var/www/khattakbits/shared/.env
```

### 7.2 Product app — `/var/www/mizaanbits/shared/.env`

```bash
sudo -u deploy tee /var/www/mizaanbits/shared/.env > /dev/null <<'EOF'
# ═══════════════════════════════════════════════════════════════════════════
# MizaanbitsERP — multi-tenant product (path-based, 07 §3)
# ═══════════════════════════════════════════════════════════════════════════
APP_NAME="Mizaanbits ERP"
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_PREVIOUS_KEYS=
APP_URL=https://mizaanbitserp.com
APP_TIMEZONE=Asia/Karachi
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

# ── Logging ────────────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30

# ── Database ───────────────────────────────────────────────────────────────
# `central` = mizaan_central (product ka apna DB, tenants_registry + users)
DB_CONNECTION=central
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mizaan_central
DB_USERNAME=mizaan_app
DB_PASSWORD=CHANGE_ME_48_CHARS

# Provision credential — SIRF tenants:provision/tenants:destroy (09 §3.1)
# Web process se reachable nahi hona chahiye (09 §3.3 control #1)
DB_PROVISION_USERNAME=mizaan_provision
DB_PROVISION_PASSWORD=CHANGE_ME_64_CHARS

# ── Tenancy (stancl v3.10.1) ───────────────────────────────────────────────
TENANCY_DB_PREFIX=mizaan_t_
TENANCY_DB_SUFFIX=
# CacheTenancyBootstrapper OFF (07 §1.5 O2) — Cache::tags() kabhi nahi
TENANCY_CACHE_BOOTSTRAPPER=false
# tenancy.routes = false — hum apne routes likhte hain (07 §3.1)
TENANCY_ROUTES=false

# ── Session (host-only, ek host — 09 §7.3) ─────────────────────────────────
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=mb_session
SESSION_CONNECTION=central

# ── Cache / Queue / Redis ──────────────────────────────────────────────────
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=CHANGE_ME_REDIS_STRONG
REDIS_PORT=6379
REDIS_PREFIX=mb_
DB_CACHE_CONNECTION=central
DB_QUEUE_CONNECTION=central

# ── Filesystem (07 §8.3 — tenancy suffix OFF) ──────────────────────────────
FILESYSTEM_DISK=local
ATTACHMENTS_DISK=local

# ── Mail ───────────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS="no-reply@mizaanbitserp.com"
MAIL_FROM_NAME="${APP_NAME}"

# ── Security ───────────────────────────────────────────────────────────────
CSP_MODE=report-only

# ── KhattakBits SSO client (config/kb.php — 08 §2.2) ───────────────────────
KB_CP_URL=https://khattakbits.com
KB_CLIENT_ID=kb_live_mizaan_xxxxxxxxxxxxxxxx
KB_CLIENT_SECRET=CHANGE_ME_FROM_ADMIN_PANEL
KB_REDIRECT_URI=https://mizaanbitserp.com/auth/kb/callback
KB_WEBHOOK_SECRET=CHANGE_ME_SIGNING_SECRET
KB_FAIL_MODE=closed
# 72h offline grace, 24h degrade (08 §2.11.4)
KB_RESOLVE_TTL=300
KB_RESOLVE_OFFLINE_MAX_AGE_HOURS=72
KB_RESOLVE_DEGRADE_AFTER_HOURS=24

# ── Vite ───────────────────────────────────────────────────────────────────
VITE_APP_NAME="${APP_NAME}"
EOF

sudo chown deploy:www-data /var/www/mizaanbits/shared/.env
sudo chmod 640 /var/www/mizaanbits/shared/.env
```

**Product `.env` mein jo **nahi** hona chahiye:**

| Key | Kyun nahi |
|---|---|
| `PASSPORT_PRIVATE_KEY` | Product **client** hai, provider nahi (`08` §1.7). CP ke Passport keys product mein rakhna = ek aur jagah private key |
| `DB_PROVISION_*` (web process) | §3.3 control #1 — sirf CLI |
| `PAYMENTS_*` | Payments CP ka kaam hai (`06`) |
| `ALLOWED_SSO_HOSTS` | Product redirect **nahi** karta, sirf callback receive karta hai |
| `KB_SCOPES` | `config/kb.php` mein hardcoded hai (`08` §2.5). `.env` se override **na** karein — CP par registered scopes se mismatch ho to `invalid_scope` |

### 7.3 `SESSION_DOMAIN` policy — path-based ne isay simple kar diya

**Purana `02` §7.2 ka poora PSL discussion mar chuka hai.** Path-based tenancy mein:

| App | `SESSION_DOMAIN` | Kyun |
|---|---|---|
| CP `khattakbits.com` | **khali** (host-only) | Ek host. `www` → apex redirect hai (§4.3) |
| Product `mizaanbitserp.com` | **khali** (host-only) | Ek host. Tenants **paths** hain, subdomains nahi |
| `www.mizaanbitserp.com` | — | Redirect to apex (§4.4) |

**Kyun yeh behtar hai (`07` §3.7):**

| Purana (subdomain tenancy) | Naya (path-based) |
|---|---|
| `SESSION_DOMAIN=.mizaanbitserp.com` | `SESSION_DOMAIN=` (khali) |
| Cookie har subdomain ko jati hai — ek compromised tenant page doosre ki cookie parh sakta hai (same-origin policy subdomain ko alag maanti hai, cookie ko nahi) | Cookie sirf ek host ke liye — **koi subdomain leakage nahi** |
| `acme.mizaanbitserp.com` → `beta.mizaanbitserp.com` = **naya session**, re-login ya handoff chahiye | `/acme` → `/beta` = **same session**, ~0ms, koi re-login nahi |
| Handoff tokens, `sso_handoffs` table, redirect chain | Kuch nahi |
| Cross-root domain (`mizaanbitserp.com` vs `khattakbits.com`) par cookie share **impossible** — poora "Method 3 shared cookie" idea hi bekaar | Koi cross-domain cookie ki zaroorat nahi — SSO OAuth2 se hota hai |

> **`SESSION_ENCRYPT=true`** — cookie encrypt hoti hai, is liye `APP_KEY` rotation par **sab logout** ho jate hain (`06` §5.2.1 ka rotation impact table). Rotation maintenance window mein (§12.4).

### 7.4 `APP_PREVIOUS_KEYS` — khali rakhein (rotation ke ilawa)

```bash
# ✅ Normal state
APP_PREVIOUS_KEYS=

# ⏳ Rotation ke dauraan hi (06 §5.2.1)
APP_KEY=base64:NEWKEY...
APP_PREVIOUS_KEYS=base64:OLDKEY...
```

> **`APP_PREVIOUS_KEYS` bhara hua chhorna ek security debt hai** — purana key tab tak zinda hai jab tak woh list mein hai. `06` §5.2.2 entry 5 isay checklist item banata hai (§14.3).

---

## 8. Pehla deploy (manual — Deployer se pehle)

### 8.1 Directory layout

```text
/var/www/
├── khattakbits/
│   ├── current -> releases/20261114143000
│   ├── releases/
│   │   ├── 20261114143000/
│   │   └── 20261113120000/
│   ├── shared/
│   │   ├── .env                     (640 deploy:www-data)
│   │   └── storage/
│   │       ├── app/private/
│   │       ├── app/public/
│   │       ├── framework/{cache,sessions,views}/
│   │       └── logs/
│   └── .dep/
├── mizaanbits/
│   └── (same structure)
└── letsencrypt/                     (ACME webroot, §5.1)
```

```bash
for APP in khattakbits mizaanbits; do
  sudo mkdir -p /var/www/$APP/releases
  sudo mkdir -p /var/www/$APP/shared/storage/{app/private,app/public,framework/{cache,sessions,views},logs}
  sudo chown -R deploy:www-data /var/www/$APP
  sudo chmod -R 775 /var/www/$APP/shared/storage
done

# ── VERIFY ────────────────────────────────────────────────────────────────
ls -ld /var/www/*/shared/storage
```

### 8.2 Clone → composer → key → migrate

```bash
APP=khattakbits
REL=$(date +%Y%m%d%H%M%S)

# ── 1. Clone ───────────────────────────────────────────────────────────────
sudo -u deploy git clone --depth 1 git@github.com:khattakbits/control-plane.git \
  /var/www/$APP/releases/$REL

cd /var/www/$APP/releases/$REL

# ── 2. Shared .env + storage link ──────────────────────────────────────────
sudo -u deploy ln -sfn /var/www/$APP/shared/.env .env
sudo -u deploy rm -rf storage
sudo -u deploy ln -sfn /var/www/$APP/shared/storage storage

# ── 3. PHP deps ────────────────────────────────────────────────────────────
sudo -u deploy composer install \
  --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

# ── 4. Platform requirements — sodium/openssl gate (§1.8) ──────────────────
sudo -u deploy composer check-platform-reqs
# Agar ext-sodium missing ho to YAHAN fail hoga — aage nahi barhein

# ── 5. APP_KEY (sirf PEHLI dafa) ───────────────────────────────────────────
sudo -u deploy php artisan key:generate --force
sudo -u deploy grep '^APP_KEY=' /var/www/$APP/shared/.env
# → APP_KEY=base64:...   ← ABHI §12.3 ka offline backup lein

# ── 6. Central migrations ──────────────────────────────────────────────────
sudo -u deploy php artisan migrate --force --no-interaction
# → 4 central migrations (2 core + 2 host) + Passport ke 5

# ── 7. Seed (roles/permissions + super-admin) ──────────────────────────────
sudo -u deploy php artisan db:seed --force --no-interaction

# ── 8. Storage link ────────────────────────────────────────────────────────
sudo -u deploy php artisan storage:link

# ── 9. Cache warmup ────────────────────────────────────────────────────────
sudo -u deploy php artisan optimize
sudo -u deploy php artisan permission:cache-reset

# ── 10. Symlink current ────────────────────────────────────────────────────
sudo -u deploy ln -sfn /var/www/$APP/releases/$REL /var/www/$APP/current

# ── 11. FPM reload (OPcache validate_timestamps=0) ─────────────────────────
sudo systemctl reload php8.5-fpm
```

**Product app ke liye same, magar `passport:keys` nahi (§8.3) aur `tenants:migrate` bhi nahi** (abhi koi tenant nahi).

> **`migrate --force` aur `db:seed --force`** — `--force` ke baghair production mein Laravel confirmation maangta hai aur non-interactive shell par **fail** karta hai. Deployer bhi `--no-interaction` ke saath chalata hai.

> **`php artisan optimize`** config + routes + views + events cache karta hai. Iske **baad** koi `.env` change karne par `php artisan config:clear && php artisan optimize` dobara chalana padega — warna purani cached values chalti rahengi.

### 8.3 Passport keys (sirf CP)

```bash
cd /var/www/khattakbits/current

# ── Keys generate (storage/ mein, web root ke bahar) ───────────────────────
sudo -u deploy php artisan passport:keys --force
sudo -u deploy chmod 600 storage/oauth-private.key
sudo -u deploy chmod 644 storage/oauth-public.key

# ── VERIFY ────────────────────────────────────────────────────────────────
ls -l storage/oauth-*.key
# → -rw------- 1 deploy www-data ... oauth-private.key    ← 600
# → -rw-r--r-- 1 deploy www-data ... oauth-public.key     ← 644

# ── Personal access client (admin UI ke liye) ──────────────────────────────
sudo -u deploy php artisan passport:client --personal --name="KhattakBits Personal"
# → Client ID: 9xxxxxxx-xxxx-...
# → Client secret: xxxxxxxx...

# ── Confidential clients per product ADMIN UI se banayenge (05 §1.3) ───────
#    Yahi se KB_CLIENT_ID / KB_CLIENT_SECRET milte hain (08 §1.7)
```

**Passport keys ka backup — `APP_KEY` ke saath, magar alag:**

| File | Kya | Backup |
|---|---|---|
| `storage/oauth-private.key` | JWT signing (RS256) | ✅ **offline**, `APP_KEY` ke saath |
| `storage/oauth-public.key` | Verification | ✅ same |
| `APP_KEY` | `encrypted` cast + signed URLs | ✅ **offline, backup bucket se bahar** (§12.3) |

> **`passport:keys` ko har deploy par na chalayein.** Naya key = saare issued tokens invalid = **har user logout**. Sirf ek dafa (ya rotation par, jo ek maintenance window hai).
>
> **`--force` se keys overwrite hoti hain** — agar ghalti se chal gayi to purane tokens sab dead. Deploy script se isay **hata dein** (Deployer config §11.1 mein nahi hai — deliberately).

### 8.4 Admin user

```bash
cd /var/www/khattakbits/current

# ── Super-admin (interactive — ek dafa, manually) ──────────────────────────
sudo -u deploy php artisan tinker
```

```php
// tinker ke andar
$u = \KhattakBits\Core\Models\User::create([
    'name'      => 'Khattak',
    'email'     => 'admin@khattakbits.com',
    'password'  => bcrypt(\Illuminate\Support\Str::password(24)),
    'is_active' => true,
]);
$u->assignRole('super-admin');       // spatie HasRoles, teams => false
echo $u->id, PHP_EOL;
exit;
```

> **`markEmailAsVerified()` na likhein.** `User` model `MustVerifyEmail` **implement nahi karta** — `khattakbits-core/src/Models/User.php:5` par woh `use` line **commented** hai aur class declaration `implements` ke baghair hai (`:19`). Is liye woh method maujood hi nahi. `email_verified_at` column hai magar verification enforce nahi hoti — SSO se aane wale users ke liye yahi theek hai (`05` §5.1.1: identity CP ki hai, product verify nahi karta).

> **`is_active` column** `EnsureUserActive` middleware parhta hai. `false` = login ke baad foran logout. Admin user ke liye `true` lazmi.

```bash
# ── Password reset link bhejein (plaintext password chat mein na bhejein) ──
sudo -u deploy php artisan tinker --execute="
  \Illuminate\Support\Facades\Password::sendResetLink(['email' => 'admin@khattakbits.com']);
"
```

> **`users.password` NULLABLE hona chahiye** — SSO-only users ke liye (`05` §5.1.1, `08` §6.3). Agar migration mein `NOT NULL` hai to SSO callback par `SQLSTATE[23000]: Column 'password' cannot be null` aayega. **`08` §6.3 ka decision: core package mein nullable.**

### 8.5 Pehla tenant — `tenants:provision`

**Yeh product app par chalta hai, CP par nahi** (§3.1).

```bash
cd /var/www/mizaanbits/current

# ── Dry-run pehle (agar command support karti hai) ────────────────────────
sudo -u deploy php artisan tenants:provision --help

# ── Provision ─────────────────────────────────────────────────────────────
sudo -u deploy php artisan tenants:provision \
  --slug=acme \
  --name="Acme Traders" \
  --owner-email=owner@acme.example \
  --confirm=acme
```

**Kya hota hai (`07` §6, step by step):**

| Step | Kya | DB effect |
|---|---|---|
| 1 | Row banti hai | `tenants` (`provisioning_state=creating_db`) |
| 2 | `CREATE DATABASE mizaan_t_<ULID>` | `provision` connection se (§3.2) |
| 3 | Tenant migrations | 10 tenant migrations |
| 4 | Seed (roles/permissions) | tenant DB |
| 5 | Owner mapping | `kb_user_tenant` |
| 6 | OAuth client | `product_oauth_clients` |
| 7 | `provisioning_state=active` | `tenants` |

```bash
# ── VERIFY ────────────────────────────────────────────────────────────────
sudo -u deploy php artisan tenants:list

# DB bani?
sudo mysql -e "SHOW DATABASES LIKE 'mizaan\_t\_%';"

# Migrations chalin?
sudo -u deploy php artisan tenants:migrate --tenants=$(sudo -u deploy php artisan tenants:list --json | jq -r '.[0].id') --pretend
# → "Nothing to migrate" ✅
```

**Agar provisioning fail ho (`provisioning_state=failed`):**

```bash
# ── tenants:sweep-stuck 15 min baad khud pakad lega (§10.1) ────────────────
# Manual resume — usi step se, skipped steps call hi nahi hote (07 §6.3)
sudo -u deploy php artisan tenants:provision --retry=<tenant-id>

# ── Stuck rows dekhein ────────────────────────────────────────────────────
sudo -u deploy php artisan tenants:sweep-stuck --dry-run
```

> **`--retry` `failed_at_state` se resume karta hai** — fresh attempt nahi. Fresh attempt ka matlab hota `CREATE DATABASE` dobara (jo fail hoga kyunke DB maujood hai) aur phir manual cleanup. `07` §6.3/§6.5 isi liye `failed_at_state` column rakhta hai.

### 8.6 `tenants:migrate` — deploy pipeline ka hissa

```bash
cd /var/www/mizaanbits/current

# ── Saare tenants ─────────────────────────────────────────────────────────
sudo -u deploy php artisan tenants:migrate --force

# ── Ek tenant ─────────────────────────────────────────────────────────────
sudo -u deploy php artisan tenants:migrate --tenants=01HX... --force

# ── Pending dekhein (kuch nahi chalega) ───────────────────────────────────
sudo -u deploy php artisan tenants:migrate --tenants=01HX... --pretend
```

**`--path` ka gotcha (`07` §10.1):**

`tenants:migrate` ka `--path` ek **`VALUE_IS_ARRAY`** option hai. Yani:

```bash
# ✅ Sahi — multiple paths comma ke baghair, repeated flag
php artisan tenants:migrate --path=database/migrations/tenant --path=database/migrations/tenant-extra

# ❌ GHALAT — config se set karne ki koshish
config(['tenancy.migration_parameters.--path' => 'database/migrations/tenant']);
# Yeh KAAM NAHI karta — parameter name `--path` hai, config key nahi
```

**Failure handling (`07` §10.1):** `tenants:migrate` beech mein fail ho to **baaki tenants nahi chalte**. Is liye batch mein chalayein:

```bash
cd /var/www/mizaanbits/current
FAILED=0
for T in $(php artisan tenants:list --json | jq -r '.[].id'); do
  php artisan tenants:migrate --tenants="$T" --force \
    || { echo "FAILED: $T" | tee -a storage/logs/migrate-failures.log; FAILED=$((FAILED+1)); }
done
echo "Failures: $FAILED"
[ "$FAILED" -eq 0 ] || exit 1
```

> **Order hamesha: central pehle, phir tenants** (`07` §10.1). Ulta karne par agar `tenants.slug` column abhi na ho to `SlugPathTenantResolver` fail karega aur **saare tenants 500** denge.

---

# PART B — Operations

## 9. Supervisor — queues

### 9.0 `QUEUE_CONNECTION` — `sync` **kabhi nahi** production mein

`06` §5.2.2 entry 8. Wajah:

| `QUEUE_CONNECTION=sync` | `QUEUE_CONNECTION=redis` |
|---|---|
| `DB::afterCommit` + queued job **request ke andar** chalta hai | Job queue mein jata hai, worker chalata hai |
| Provisioning (DB create + migrate + seed, ~20–60s) admin ke **approve button** ko 60s block karta hai → gateway timeout → admin dobara click → **duplicate tenant** | Approve turant return karta hai, progress admin UI par |
| `06` §2.7.3 ka `afterCommit` pattern bekaar | Sahi chalta hai |

Aaj `.env.example` mein `QUEUE_CONNECTION=database` hai — production mein `redis` (§7.1, §7.2).

### 9.1 CP queues — chaar alag programs

**Kyun alag:** ek slow provisioning job `webhooks` queue ko block na kare. Redis par queues independent hain magar **worker ek waqt mein ek queue parhta hai** — is liye alag worker processes chahiye.

```bash
sudo tee /etc/supervisor/conf.d/kb-worker.conf > /dev/null <<'EOF'
; ═══════════════════════════════════════════════════════════════════════════
; 1) DEFAULT — notifications, emails ke ilawa sab kuch
; ═══════════════════════════════════════════════════════════════════════════
[program:kb-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/khattakbits/current/artisan queue:work redis
        --queue=default
        --sleep=3 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/khattakbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/khattakbits/shared/storage/logs/worker-default.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

; ═══════════════════════════════════════════════════════════════════════════
; 2) WEBHOOKS — outbound delivery (05 §4.2), HMAC + retry ladder
;    Alag queue kyun: product down ho to retries default queue ko na roken
; ═══════════════════════════════════════════════════════════════════════════
[program:kb-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/khattakbits/current/artisan queue:work redis
        --queue=webhooks
        --sleep=2 --tries=5 --max-time=3600 --timeout=30
        --backoff=10,30,120,600
directory=/var/www/khattakbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/khattakbits/shared/storage/logs/worker-webhooks.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

; ═══════════════════════════════════════════════════════════════════════════
; 3) PROVISIONING — apni queue, apna connection, tries=1
;
;    tries=1 LAZMI: provisioning retry `tenants:provision --retry` se hota hai
;    jo failed_at_state se resume karta hai (07 §6.3). Auto-retry fresh attempt
;    karta hai → CREATE DATABASE fail (DB maujood hai) → orphan state.
;
;    timeout=330 > job timeout 300 (07 §6.2) — worker job ko beech mein
;    nahi maarta. Agar worker timeout job se kam ho to job dobara chalti hai
;    aur DB create dobara try hota hai.
; ═══════════════════════════════════════════════════════════════════════════
[program:kb-provisioning]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/khattakbits/current/artisan queue:work redis
        --queue=provisioning
        --sleep=3 --tries=1 --max-time=3600 --timeout=330
directory=/var/www/khattakbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/khattakbits/shared/storage/logs/worker-provisioning.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

; ═══════════════════════════════════════════════════════════════════════════
; 4) MAIL — separate kyun: SMTP slow ho to baaki queue na ruke
; ═══════════════════════════════════════════════════════════════════════════
[program:kb-mail]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/khattakbits/current/artisan queue:work redis
        --queue=mail
        --sleep=3 --tries=3 --max-time=3600 --timeout=60
directory=/var/www/khattakbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/khattakbits/shared/storage/logs/worker-mail.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF
```

### 9.2 Product queues — do programs

```bash
sudo tee /etc/supervisor/conf.d/mizaan-worker.conf > /dev/null <<'EOF'
; ═══════════════════════════════════════════════════════════════════════════
; DEFAULT — tenant jobs. QueueTenancyBootstrapper payload mein tenant_id
; dalta hai aur worker tenancy initialize karta hai (07 §8.4).
; ═══════════════════════════════════════════════════════════════════════════
[program:mizaan-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mizaanbits/current/artisan queue:work redis
        --queue=default
        --sleep=3 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/mizaanbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/mizaanbits/shared/storage/logs/worker-default.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600

; ═══════════════════════════════════════════════════════════════════════════
; PROVISIONING — product side (08 §2 config; tenants:provision)
; ═══════════════════════════════════════════════════════════════════════════
[program:mizaan-provisioning]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mizaanbits/current/artisan queue:work redis
        --queue=provisioning
        --sleep=3 --tries=1 --max-time=3600 --timeout=330
directory=/var/www/mizaanbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/mizaanbits/shared/storage/logs/worker-provisioning.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=5
stopwaitsecs=3600
EOF
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
# → kb-default_00 RUNNING, kb-default_01 RUNNING, kb-webhooks_00 RUNNING,
#   kb-provisioning_00 RUNNING, kb-mail_00 RUNNING,
#   mizaan-default_00..02 RUNNING, mizaan-provisioning_00 RUNNING
```

### 9.3 `queue:restart` — har deploy par

```bash
php artisan queue:restart
```

> **`stopwaitsecs=3600`** `--max-time=3600` ke saath match karta hai. Iske baghair Supervisor deploy par worker ko **SIGKILL** karta hai aur in-flight job adhoora mar jata hai — aur provisioning job ke case mein woh `migrating` par phansi row chhod deta hai (§10.1 ka sweeper isi ke liye hai).

> **`queue:restart` Deployer mein `after('deploy:symlink')` hai** (§11.1). Manually bhi yaad rakhein: worker purana code chalata rehta hai jab tak restart na ho (OPcache CLI + already-loaded classes).

---

## 10. Scheduler

### 10.1 CP schedule — `routes/console.php`

```php
<?php
// /var/www/khattakbits/current/routes/console.php

use Illuminate\Support\Facades\Schedule;

// ── Maujood (VERIFIED — routes/console.php:12-13) ─────────────────────────
Schedule::command('bin:purge')->dailyAt('03:00');
Schedule::command('emails:prune')->dailyAt('03:30');

// ── Subscription engine (06 §2.3) — SIRF CP PAR ───────────────────────────
Schedule::command('subscriptions:expire')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// 06 §5.2.2 entry 7 — renewal reminders
Schedule::command('subscriptions:remind')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// ── Webhook retry ladder (05 §4.2) ────────────────────────────────────────
Schedule::command('webhooks:retry')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// ── Stuck provisioning sweeper (07 §6.5) — har 5 min ──────────────────────
Schedule::command('tenants:sweep-stuck --minutes=15')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ── Token hygiene (05 §3.5) ───────────────────────────────────────────────
Schedule::command('oauth:prune-tokens')->dailyAt('04:00');
Schedule::command('passport:purge --revoked --expired')->dailyAt('04:15');

// ── Payments (06 §5.2.2 entry 7) ──────────────────────────────────────────
Schedule::command('payments:sla-alert')->hourlyAt(15);
Schedule::command('payments:prune-callbacks')->dailyAt('04:30');

// ── Entitlement snapshot refresh (04 §3.3) ────────────────────────────────
Schedule::command('tenants:sync-entitlements')->hourly()->withoutOverlapping();
```

### 10.2 Product schedule — `routes/console.php`

```php
<?php
// /var/www/mizaanbits/current/routes/console.php

use Illuminate\Support\Facades\Schedule;

// ── Tenant data purge — `tenants:run` se HAR tenant par (07 §8.5) ─────────
// withoutOverlapping() NAHI: ek tenant fail ho to sab block ho jayenge
Schedule::command('tenants:run bin:purge --tenants=all')->dailyAt('03:00');
Schedule::command('tenants:run emails:prune --tenants=all')->dailyAt('03:30');

// ── KB subscription snapshot refresh (08 §2.12) ───────────────────────────
Schedule::command('kb:refresh-entitlements')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ── Webhook retry (product → CP ke liye nahi; CP → product ke liye CP par hai) ──
Schedule::command('kb:retry-failed-webhooks')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

> **`tenants:run` par `withoutOverlapping()` na lagayein** (`07` §8.5) — ek tenant fail ho to poora batch block ho jata hai. `onOneServer()` bhi na lagayein — woh `file` cache par kaam nahi karta aur single-server par bekaar hai.

### 10.3 Cron entries

```bash
sudo tee /etc/cron.d/kb-scheduler > /dev/null <<'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# CP — subscription tick, webhooks, sweeper (09 §10.1)
* * * * * deploy cd /var/www/khattakbits/current && php artisan schedule:run >> /dev/null 2>&1
EOF

sudo tee /etc/cron.d/mizaan-scheduler > /dev/null <<'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# Product — tenant purge, snapshot refresh (09 §10.2)
* * * * * deploy cd /var/www/mizaanbits/current && php artisan schedule:run >> /dev/null 2>&1
EOF

sudo chmod 644 /etc/cron.d/kb-scheduler /etc/cron.d/mizaan-scheduler
```

### 10.4 Verify

```bash
cd /var/www/khattakbits/current && sudo -u deploy php artisan schedule:list
cd /var/www/mizaanbits/current  && sudo -u deploy php artisan schedule:list

# ── Kya scheduler actually chal raha hai? ─────────────────────────────────
sudo -u deploy php artisan schedule:test          # interactive, sirf local
tail -50 /var/www/khattakbits/shared/storage/logs/laravel-$(date +%F).log | grep schedule

# ── `withoutOverlapping` lock stuck hai? (deploy ke baad common) ──────────
sudo -u deploy php artisan schedule:clear-cache
```

> **Subscription tick sirf CP par kyun:** `subscriptions:expire` `subscriptions` table ko mutate karta hai jo **CP central** mein hai. Product par chalane ka koi matlab nahi — woh apni copy rakhta hai jo `resolve` se aati hai (`08` §2.11). Do jagah chalane se do different state machines ban jayenge.

> **`schedule:clear-cache`** — `withoutOverlapping()` `cache` lock use karta hai. Agar deploy ke dauraan koi command beech mein mari aur lock release nahi hua, to woh command **hamesha** skip hoti rahegi. Yeh silent failure hai jo sirf `schedule:list` ke last-run column se pata chalta hai.

---

## 11. Zero-downtime, rollback, CI/CD

### 11.1 Deployer config

```bash
cd /e/LaraProjects/saas-starter
composer require --dev deployer/deployer
```

`deploy.php` (repo root mein commit karein):

```php
<?php

namespace Deployer;

require 'recipe/laravel.php';

set('application', 'khattakbits');
set('repository', 'git@github.com:khattakbits/control-plane.git');
set('keep_releases', 5);
set('git_tty', false);
set('default_timeout', 900);        // composer + migrate ke liye

// ── Shared ─────────────────────────────────────────────────────────────────
set('shared_dirs', ['storage']);
set('shared_files', ['.env']);
set('writable_dirs', ['storage', 'bootstrap/cache']);

// ── Frontend CI mein build hota hai (09 §11.4) ─────────────────────────────
set('npm_build', false);

host('kb-prod-01')
    ->setHostname('203.0.113.10')
    ->setRemoteUser('deploy')
    ->setDeployPath('/var/www/khattakbits')
    ->setIdentityFile('~/.ssh/kb_prod_ed25519')
    ->set('branch', 'main');

// ═══════════════════════════════════════════════════════════════════════════
// Tasks
// ═══════════════════════════════════════════════════════════════════════════

desc('Upload prebuilt Vite assets (CI artifact)')
task('deploy:upload_build', function () {
    if (is_dir('public/build')) {
        upload('public/build/', '{{release_path}}/public/build/');
    } else {
        warning('public/build missing — Vite manifest nahi milega');
    }
});

desc('Platform requirements — sodium/openssl gate (09 §1.8)')
task('deploy:check_platform', function () {
    run('cd {{release_path}} && {{bin/composer}} check-platform-reqs');
});

desc('Reload PHP-FPM (OPcache validate_timestamps=0)')
task('php-fpm:reload', function () {
    run('sudo systemctl reload php8.5-fpm');
});

desc('Graceful queue worker restart')
task('queue:restart', function () {
    run('{{bin/php}} {{release_path}}/artisan queue:restart');
});

desc('Central migrations (hamesha tenants se PEHLE — 07 §10.1)')
task('artisan:migrate', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate --force --no-interaction');
});

desc('Tenant migrations — batch, fail par pipeline fail (09 §8.6)')
task('tenants:migrate', function () {
    run('cd {{release_path}} && for T in $({{bin/php}} artisan tenants:list --json | jq -r ".[].id"); do '
        .'{{bin/php}} artisan tenants:migrate --tenants="$T" --force '
        .'|| { echo "FAILED: $T"; exit 1; }; done');
});

desc('Health check — deploy fail ho to rollback')
task('deploy:healthcheck', function () {
    $code = trim(run('curl -s -o /dev/null -w "%{http_code}" https://khattakbits.com/up'));
    if ($code !== '200') {
        throw new \RuntimeException("Health check failed: HTTP $code");
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// Hooks — ORDER LOAD-BEARING
// ═══════════════════════════════════════════════════════════════════════════
after('deploy:update_code', 'deploy:upload_build');
after('deploy:update_code', 'deploy:check_platform');
after('deploy:symlink', 'artisan:migrate');       // 1. central
after('artisan:migrate', 'queue:restart');        // 2. workers purana code chhod dein
after('queue:restart', 'php-fpm:reload');         // 3. naya code serve ho
after('php-fpm:reload', 'deploy:healthcheck');    // 4. verify

fail('deploy', 'deploy:rollback');
after('deploy:failed', 'deploy:unlock');
```

**Product app ke liye `deploy.php` ka farq:**

```php
set('application', 'mizaanbits');
host('kb-prod-01')
    ->setDeployPath('/var/www/mizaanbits')
    // ...

// Central ke BAAD tenants
after('artisan:migrate', 'tenants:migrate');
after('tenants:migrate', 'queue:restart');
```

### 11.2 Release sequence — aur kyun yeh order

```text
1. deploy:update_code       naya release dir
2. deploy:upload_build      public/build (CI artifact)
3. deploy:check_platform    composer check-platform-reqs  ← sodium gate
4. deploy:vendors           composer install --no-dev
5. deploy:symlink           current → naya release
6. artisan:migrate          CENTRAL migrations
7. tenants:migrate          TENANT migrations
8. queue:restart            workers naye code par
9. php-fpm:reload           OPcache clear
10. deploy:healthcheck      /up par 200?
```

**Teen order rules jo todne par outage hota hai:**

| Rule | Todne par |
|---|---|
| **Central migrations tenants se pehle** (`07` §10.1) | `tenants.slug` column na ho to `SlugPathTenantResolver` fail → **saare tenants 500** |
| **`queue:restart` `php-fpm:reload` se pehle** | FPM naya code serve karta hai, workers purana → worker naye schema par purani code chalata hai. Provisioning job ke case mein `failed_at_state` column na ho to job crash |
| **`deploy:symlink` migrations se pehle** | Migrations `{{release_path}}` par chalti hain (naya code), magar agar symlink baad mein ho to naya code serve nahi hota — 2 min tak purana code + naya schema |

**Downtime kitna:** step 6–9 ke dauraan ~2–5s (FPM reload). **Zero-downtime ke liye:** migrations **additive** rakhein (`06` §2.3) — naya column nullable, naya table. Purana code naye schema par chalta rahega.

> **`NOT VERIFIED` — `tenants:migrate` rollback.** Agar step 7 fail ho, Deployer `deploy:rollback` chalata hai jo **code** rollback karta hai, **DB nahi**. Kuch tenants migrated, kuch nahi. Recovery: `tenants:migrate` dobara chalayein (idempotent hai), ya problematic tenant ko `--tenants=` se alag karein. **Pehli dafa staging par test karein.**

### 11.3 Rollback

```bash
dep rollback              # ek release peeche
dep rollback --step=2     # do releases peeche
```

**Rollback ke saath kya kya hota hai:**

| Cheez | Rollback hoti hai? |
|---|---|
| Code (`current` symlink) | ✅ |
| `public/build` (CI artifact) | ✅ (release ke andar hai) |
| Composer packages | ✅ (`vendor` release ke andar hai) |
| **Central DB migrations** | ❌ **Nahi** |
| **Tenant DB migrations** | ❌ **Nahi** |
| Queue jobs | ❌ Nahi (naye code ki jobs purane worker par) |
| Redis cache | ❌ Nahi (stale keys) |

**Is liye rule (`06` §2.3):** production mein sirf **additive** migrations. Column drop/rename do releases mein:

```text
Release N   → code se column ka use hatao (column DB mein rehne do)
Release N+1 → column drop karo
```

```bash
# ── Rollback ke baad LAZMI ────────────────────────────────────────────────
php artisan queue:restart
php artisan optimize:clear && php artisan optimize
sudo systemctl reload php8.5-fpm
```

> **Rollback ke baad `optimize:clear`** — config cache purane release ka `.env` values hold karta hai. `storage/` shared hai, is liye `bootstrap/cache/config.php` **release ke andar** hai magar `storage/framework/cache` shared hai. Clear karna safe hai.

### 11.4 GitHub Actions — `sodium` + private packages

```yaml
# .github/workflows/deploy.yml
name: CI/CD

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true

jobs:
  test:
    name: Test (PHP 8.5)
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          # ── sodium LAZMI (05 §0.2.2) — Passport iske baghair install fail ──
          extensions: mbstring, pdo_sqlite, sqlite3, pdo_mysql, gd, zip, intl, bcmath, sodium
          coverage: none

      - name: Verify sodium + openssl
        run: |
          php -m | grep -E "^(sodium|openssl)$"
          php -r 'var_dump(function_exists("sodium_crypto_sign_verify_detached"));'

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache
          key: composer-${{ hashFiles('composer.lock') }}

      # ── Private packages (09 §6.3) ────────────────────────────────────────
      - name: Configure Composer auth
        run: |
          mkdir -p ~/.composer
          cat > ~/.composer/auth.json <<'JSON'
          {
            "github-oauth": {
              "github.com": "${{ secrets.COMPOSER_GITHUB_TOKEN }}"
            }
          }
          JSON
          chmod 600 ~/.composer/auth.json

      - name: Install deps
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Check platform reqs
        run: composer check-platform-reqs

      - name: Prepare env
        run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite

      - name: Run tests
        run: php artisan test --parallel

      - name: Pint
        run: ./vendor/bin/pint --test

  build:
    name: Build assets
    runs-on: ubuntu-24.04
    needs: test
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      # ── @khattakbits/ui private npm (09 §6.3) ─────────────────────────────
      - name: Configure npm auth
        run: |
          cat > .npmrc <<'NPMRC'
          @khattakbits:registry=https://npm.pkg.github.com
          //npm.pkg.github.com/:_authToken=${NPM_TOKEN}
          always-auth=true
          NPMRC
        env:
          NPM_TOKEN: ${{ secrets.NPM_TOKEN }}

      - run: npm ci
      - run: npm run build

      - uses: actions/upload-artifact@v4
        with:
          name: build-assets
          path: public/build
          retention-days: 7

  deploy:
    name: Deploy to production
    runs-on: ubuntu-24.04
    needs: [test, build]
    if: github.ref == 'refs/heads/main'
    environment: production
    steps:
      - uses: actions/checkout@v4

      - uses: actions/download-artifact@v4
        with:
          name: build-assets
          path: public/build

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'

      - name: Composer auth
        run: |
          mkdir -p ~/.composer
          printf '{"github-oauth":{"github.com":"%s"}}' "${{ secrets.COMPOSER_GITHUB_TOKEN }}" \
            > ~/.composer/auth.json

      - run: composer install --no-dev --prefer-dist --no-interaction

      - name: Configure SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/kb_prod_ed25519
          chmod 600 ~/.ssh/kb_prod_ed25519
          ssh-keyscan -H "${{ secrets.DEPLOY_HOST }}" >> ~/.ssh/known_hosts

      - name: Deploy
        run: ./vendor/bin/dep deploy production --no-interaction
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
```

**Secrets (repo → Settings → Secrets → Actions):**

| Secret | Value | Scope |
|---|---|---|
| `DEPLOY_SSH_KEY` | `deploy` user ki private key (ed25519) | Deploy |
| `DEPLOY_HOST` | Server IP | Deploy |
| `COMPOSER_GITHUB_TOKEN` | Fine-grained PAT, `contents:read` on `khattakbits/core` + `khattakbits/sso-client` | CI + Deploy |
| `NPM_TOKEN` | Fine-grained PAT, `read:packages` | Build |

> **`COMPOSER_GITHUB_TOKEN` fine-grained PAT ho, classic nahi.** Classic PAT poore account ke saare repos par `repo` scope deta hai — CI mein woh over-privileged hai. Fine-grained PAT sirf do repos par `contents:read`.
>
> **Yeh token CI mein `auth.json` se aa raha hai, deploy key se nahi** — kyunke GitHub Actions runner par SSH deploy key register karna possible hai magar `ssh-agent` setup chahiye. `auth.json` + HTTPS URL simpler hai. Server par (§6.3) **dono** — deploy key clone ke liye.

### 11.5 sudoers — deploy user ko sirf reload

```bash
sudo tee /etc/sudoers.d/deploy-kb > /dev/null <<'EOF'
# deploy user sirf yeh do commands bina password chala sakta hai.
# `systemctl restart` NAHI — sirf `reload` (graceful, in-flight requests finish).
deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.5-fpm
deploy ALL=(root) NOPASSWD: /bin/systemctl reload nginx
EOF
sudo chmod 440 /etc/sudoers.d/deploy-kb
sudo visudo -c

# ── VERIFY ────────────────────────────────────────────────────────────────
sudo -u deploy sudo -n systemctl reload php8.5-fpm && echo "RELOAD OK"
sudo -u deploy sudo -n systemctl restart php8.5-fpm 2>&1 | head -1
# → "sudo: a password is required"  ✅ (restart allowed nahi)
```

---

## 12. Backups

### 12.1 Kya backup hota hai

| Cheez | Kahan | Kitni ahem | Retention |
|---|---|---|---|
| **CP central DB** (`khattakbits`) | MySQL | 🔴 Critical — subscriptions, invoices, users | 30 din daily + 12 monthly |
| **Product central DB** (`mizaan_central`) | MySQL | 🔴 Critical — `tenants`, `kb_user_tenant` | 30 din daily |
| **Saare tenant DBs** (`mizaan_t_*`) | MySQL | 🔴 Critical — customer ka **poora business data** | 30 din daily + 12 monthly |
| `storage/app/private` (attachments) | Filesystem | 🟠 High | 30 din |
| **Payment proofs** (`06` §5.4) | Filesystem | 🔴 **1 saal** (regulatory) | **365 din** |
| `.env` files | Filesystem | 🟠 High (encrypted) | 30 din |
| `oauth-private.key` | Filesystem | 🔴 Critical | Har deploy |
| **`APP_KEY`** | **Password manager** | 🔴 **Critical, irreplaceable** | Forever (§12.3) |

> **Tenant DB backup ka scale:** 200 tenants × ~15 MB dump = 3 GB/day compressed ~600 MB. Retention 30 din = ~18 GB. `/var/backups` usi disk par hai (§1.1) — 80 GB disk par yeh 25% hai. **Off-site copy ke baad local retention 14 din kar dein.**

### 12.2 Backup script — central + **saare** tenant DBs

```bash
sudo mkdir -p /var/backups/kb && sudo chown deploy:deploy /var/backups/kb

# ── Credentials file (ps output mein password na aaye) ─────────────────────
sudo -u deploy tee /home/deploy/.kb-backup.cnf > /dev/null <<'EOF'
[client]
user=mizaan_backup
password=CHANGE_ME_BACKUP_48_CHARS
host=127.0.0.1
port=3306
EOF
sudo chmod 600 /home/deploy/.kb-backup.cnf
sudo chown deploy:deploy /home/deploy/.kb-backup.cnf
```

`/home/deploy/backup.sh`:

```bash
#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════
# KhattakBits backup — central DBs + SAARE tenant DBs + storage
# 09 §12.2
# ═══════════════════════════════════════════════════════════════════════════
set -Eeuo pipefail

STAMP=$(date +%F_%H%M)
DEST=/var/backups/kb
TMP=$(mktemp -d)
CNF=/home/deploy/.kb-backup.cnf
RETENTION_DAYS=14          # local; off-site 30/365 (12.5)
PREFIXES=("mizaan_t_" "lawbits_t_")

trap 'rm -rf "$TMP"' EXIT
mkdir -p "$DEST"

log() { echo "[$(date -Is)] $*"; }

# ═══════════════════════════════════════════════════════════════════════════
# 1. CENTRAL databases — named explicitly (prefix match NAHI)
# ═══════════════════════════════════════════════════════════════════════════
CENTRAL_DBS=(khattakbits mizaan_central)

for DB in "${CENTRAL_DBS[@]}"; do
    log "dump central: $DB"
    mysqldump --defaults-extra-file="$CNF" \
        --single-transaction --quick --no-tablespaces \
        --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "$DB" | gzip -6 > "$DEST/${DB}_${STAMP}.sql.gz"
done

# ═══════════════════════════════════════════════════════════════════════════
# 2. TENANT databases — prefix se discover
#
#    SHOW DATABASES + grep kyun, information_schema LIKE nahi:
#    LIKE mein `_` wildcard hai, is liye escaping (`\_`) chahiye jo bash
#    quoting ke saath fragile hai. grep -E `^mizaan_t_` unambiguous hai.
# ═══════════════════════════════════════════════════════════════════════════
mapfile -t ALL_DBS < <(mysql --defaults-extra-file="$CNF" -N -B -e 'SHOW DATABASES')

TENANT_DBS=()
for DB in "${ALL_DBS[@]}"; do
    for P in "${PREFIXES[@]}"; do
        [[ "$DB" == "$P"* ]] && TENANT_DBS+=("$DB") && break
    done
done

log "tenant DBs found: ${#TENANT_DBS[@]}"

if [ "${#TENANT_DBS[@]}" -eq 0 ]; then
    log "WARNING: koi tenant DB nahi mili — prefix ya grants check karein"
fi

TENANT_FAIL=0
for DB in "${TENANT_DBS[@]}"; do
    if mysqldump --defaults-extra-file="$CNF" \
        --single-transaction --quick --no-tablespaces \
        --routines --triggers --events \
        --default-character-set=utf8mb4 \
        "$DB" 2>>"$DEST/backup-errors.log" | gzip -6 > "$DEST/tenant_${DB}_${STAMP}.sql.gz"; then
        :
    else
        log "FAILED tenant: $DB"
        TENANT_FAIL=$((TENANT_FAIL + 1))
        rm -f "$DEST/tenant_${DB}_${STAMP}.sql.gz"
    fi
done

# ═══════════════════════════════════════════════════════════════════════════
# 3. STORAGE — attachments (private disk) + payment proofs (1 saal)
# ═══════════════════════════════════════════════════════════════════════════
log "storage tar"
tar -czf "$DEST/storage_${STAMP}.tar.gz" \
    -C /var/www/khattakbits/shared storage/app/private \
    -C /var/www/mizaanbits/shared  storage/app/private \
    2>/dev/null || log "WARNING: storage tar partial"

# ── Payment proofs: alag, 365 din retention (06 §5.4) ─────────────────────
if [ -d /var/www/khattakbits/shared/storage/app/private/payment-proofs ]; then
    tar -czf "$DEST/payment-proofs_${STAMP}.tar.gz" \
        -C /var/www/khattakbits/shared storage/app/private/payment-proofs
fi

# ═══════════════════════════════════════════════════════════════════════════
# 4. SECRETS — .env + Passport keys (encrypted)
#    ⚠️ Yeh APP_KEY ke saath WAHI bucket mein jata hai. 06 §5.2.1 ka point:
#    bucket + passphrase mil jaye to encryption ka faida khatam. Is liye
#    APP_KEY ki ASLI backup password manager mein hai (§12.3), yahan nahi.
# ═══════════════════════════════════════════════════════════════════════════
log "secrets"
tar -czf - \
    /var/www/khattakbits/shared/.env \
    /var/www/mizaanbits/shared/.env \
    /var/www/khattakbits/shared/storage/oauth-private.key \
    /var/www/khattakbits/shared/storage/oauth-public.key \
    2>/dev/null \
  | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE \
  > "$DEST/secrets_${STAMP}.tar.gz.enc"

# ═══════════════════════════════════════════════════════════════════════════
# 5. VERIFY — dump readable hai? (06 §5.2.1: bina test backup, backup nahi)
# ═══════════════════════════════════════════════════════════════════════════
log "verify"
for DB in "${CENTRAL_DBS[@]}"; do
    gzip -t "$DEST/${DB}_${STAMP}.sql.gz" \
        || { log "CORRUPT: ${DB}_${STAMP}.sql.gz"; exit 1; }
done

# Sab se chhoti tenant dump ka bhi gzip integrity check
if [ "${#TENANT_DBS[@]}" -gt 0 ]; then
    FIRST_TENANT="${TENANT_DBS[0]}"
    gzip -t "$DEST/tenant_${FIRST_TENANT}_${STAMP}.sql.gz" \
        || { log "CORRUPT: tenant dump"; exit 1; }
fi

# ═══════════════════════════════════════════════════════════════════════════
# 6. OFF-SITE — rclone (bucket APP_KEY se ALAG trust boundary, §12.3)
# ═══════════════════════════════════════════════════════════════════════════
if command -v rclone >/dev/null 2>&1; then
    log "off-site copy"
    rclone copy "$DEST" "kb-backup:$(hostname)" \
        --include "*_${STAMP}*" --transfers 4 --checkers 8 --retries 3 || log "WARNING: off-site failed"
else
    log "WARNING: rclone missing — backup sirf local hai"
fi

# ═══════════════════════════════════════════════════════════════════════════
# 7. RETENTION — payment proofs 365 din, baaki 14 din
# ═══════════════════════════════════════════════════════════════════════════
find "$DEST" -type f -name 'payment-proofs_*' -mtime +365 -delete
find "$DEST" -type f ! -name 'payment-proofs_*' -mtime +$RETENTION_DAYS -delete

# ═══════════════════════════════════════════════════════════════════════════
# 8. SUMMARY
# ═══════════════════════════════════════════════════════════════════════════
SIZE=$(du -sh "$DEST" | cut -f1)
log "backup OK: $STAMP | tenants=${#TENANT_DBS[@]} | failed=${TENANT_FAIL} | dir=${SIZE}"

[ "$TENANT_FAIL" -eq 0 ] || { log "ERROR: $TENANT_FAIL tenant dumps failed"; exit 1; }
```

```bash
chmod +x /home/deploy/backup.sh

# ── Pehli dafa manually chalayein (passphrase env se) ─────────────────────
sudo -u deploy BACKUP_PASSPHRASE='test-only' /home/deploy/backup.sh
ls -lh /var/backups/kb/ | head -10

# ── Cron ──────────────────────────────────────────────────────────────────
sudo tee /etc/cron.d/kb-backup > /dev/null <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
# BACKUP_PASSPHRASE /etc/kb-backup.env se (root-only, 600)
30 2 * * * deploy . /etc/kb-backup.env && /home/deploy/backup.sh >> /var/log/kb-backup.log 2>&1
EOF
sudo chmod 644 /etc/cron.d/kb-backup

sudo tee /etc/kb-backup.env > /dev/null <<'EOF'
BACKUP_PASSPHRASE=CHANGE_ME_STRONG
EOF
sudo chmod 600 /etc/kb-backup.env
sudo chown root:root /etc/kb-backup.env
```

> **Payment proofs ki 1-saal retention do alag cheezon par chalti hai.** `06` §5.4 kehta hai
> "Retention: 1 saal (`payments:prune-proofs` — **Phase 2**)". Yani **delete** karne wala command
> abhi maujood nahi. Uske baghair proof files hamesha storage mein rahengi — is liye **backup side par**
> 365 din ka retention rakha gaya hai (§12.2 step 7) taake backup volume na barhe. Jab
> `payments:prune-proofs` Phase 2 mein aaye, tab **dono** jagah 365 din rakhein — warna backup mein
> woh data rahega jo storage se delete ho chuka hai.

**Kyun `mysqldump` aur `--single-transaction`:**

| Flag | Kyun |
|---|---|
| `--single-transaction` | InnoDB snapshot — dump ke dauraan tables lock nahi hoti, app chalta rehta hai |
| `--quick` | Rows ek ek stream karta hai, poori table RAM mein nahi |
| `--no-tablespaces` | `PROCESS` privilege ke baghair bhi chalta hai (defensive) |
| `--routines --triggers --events` | Views/stored procs/triggers bhi jate hain |
| `--defaults-extra-file` | Password `ps aux` mein **nahi** dikhta (`MYSQL_PWD` env bhi leak hota hai) |

> **`--single-transaction` aur `LOCK TABLES` dono?** `--single-transaction` khud lock nahi leta. Backup user ko `LOCK TABLES` is liye diya hai ke agar `--single-transaction` fail ho (MyISAM table) to fallback ho. Aaj sab InnoDB hai.

### 12.3 `APP_KEY` — offline backup (06 §5.2.1, §5.2.2 entry 2)

**Yeh backup script ka hissa NAHI hai.** Yeh manually, ek dafa, aur phir har 3 mahine verify:

```bash
# ── 1. Key nikaalein ──────────────────────────────────────────────────────
sudo -u deploy grep '^APP_KEY=' /var/www/khattakbits/shared/.env
sudo -u deploy grep '^APP_KEY=' /var/www/mizaanbits/shared/.env
# Do ALAG keys honi chahiye (per app)

# ── 2. Password manager mein daalein (1Password/Bitwarden vault) ──────────
#      PLUS ek sealed envelope safe mein. Dono, ek nahi.

# ── 3. Passport keys bhi (in ke baghair saare tokens invalid) ─────────────
sudo -u deploy cat /var/www/khattakbits/shared/storage/oauth-private.key
sudo -u deploy cat /var/www/khattakbits/shared/storage/oauth-public.key
```

**Teen rules (`06` §5.2.1):**

| Rule | Detail |
|---|---|
| **Kahan** | Password manager **+** sealed envelope. Dono |
| **Kaun** | **Kam az kam do log** — ek aap, ek woh jo aap ki non-availability mein deploy kar sake |
| **Kab verify** | **Har 3 mahine** (Phase 2 se pehle 6 mahine tha — `06` §5.2.1 ne blast radius barhne par 3 kar diya): backup se key utha kar ek known encrypted value decrypt karein |

**Kabhi nahi:**

- ❌ Git (private repo bhi nahi)
- ❌ Ticket / chat / email
- ❌ `docs/`
- ❌ DB
- ❌ **DB dump ke saath wahi bucket** ← §12.2 step 4 exactly yeh karta hai, **deliberately**, disaster recovery ke liye

> **Bucket wali copy disaster recovery hai, security backup nahi** (`06` §5.2.1). Jo `APP_KEY` ko `payment_gateway_accounts.credentials` ki encryption ki asli hijab samajh raha hai, uske liye woh hijab us waqt khatam ho jata hai jab kisi ko backup bucket + `BACKUP_PASSPHRASE` mil jaye. **Asli backup password manager mein hai.**

**Verify test (har 3 mahine):**

```bash
# Password manager se key utha kar, ek known encrypted value decrypt karein
php artisan tinker --execute="
  \$key = 'base64:KEY_FROM_PASSWORD_MANAGER';
  \$c = new \Illuminate\Encryption\Encrypter(base64_decode(substr(\$key, 7)), 'AES-256-CBC');
  \$row = \DB::table('payment_gateway_accounts')->first();
  echo \$c->decrypt(\$row->credentials, false) ? 'DECRYPT OK' : 'FAIL';
"
```

> **Yeh test production par na chalayein** — woh `.env` ka key use karta hai, backup wala nahi. Staging par, ya ek throwaway tinker session mein `APP_KEY` temporarily override kar ke.

### 12.4 `APP_KEY` rotation runbook (06 §5.2.1, §5.2.2 entry 9)

**Maintenance window mein. `APP_KEY` rotation se:**

| Cheez | Asar |
|---|---|
| Session cookies | **Sab logout** (`SESSION_ENCRYPT=true`) |
| Invoice signed links (7 din, `06` §2.9.4) | **Invalid** |
| `renew-link` tokens (30 min) | Invalid |
| Password reset links | Invalid |
| `payment_gateway_accounts.credentials` | Re-encrypt **lazmi** |
| `webhook_endpoints.secret`, `EmailAccount.password` | Re-encrypt lazmi |

**5 steps (`06` §5.2.1):**

```bash
cd /var/www/khattakbits/current

# ── 1. Naya key generate (likhta nahi, sirf print) ────────────────────────
php artisan key:generate --show
# → base64:NEWKEY...

# ── 2. .env update ────────────────────────────────────────────────────────
#   APP_KEY=base64:NEWKEY...
#   APP_PREVIOUS_KEYS=base64:OLDKEY...
sudo -u deploy sed -i "s|^APP_KEY=.*|APP_KEY=base64:NEWKEY...|" \
  /var/www/khattakbits/shared/.env
sudo -u deploy sed -i "s|^APP_PREVIOUS_KEYS=.*|APP_PREVIOUS_KEYS=base64:OLDKEY...|" \
  /var/www/khattakbits/shared/.env

# ── 3. Config reload ──────────────────────────────────────────────────────
php artisan config:clear && php artisan config:cache
sudo systemctl reload php8.5-fpm

# ── 4. VERIFY — purana data parhna chahiye (naya key + previous key) ──────
php artisan tinker --execute="
  dump(\KhattakBits\Core\Models\PaymentGatewayAccount::first()?->credentials);
"
# → array dikhna chahiye, exception NAHI

# ── 5. Re-encrypt (purane key se encrypted rows ko naye key se rewrite) ───
php artisan payments:reencrypt-secrets --dry-run    # pehle gino
php artisan payments:reencrypt-secrets              # phir chalao

# ── 6. Purana key retire — SIRF jab step 5 verify ho jaye ─────────────────
#   APP_PREVIOUS_KEYS= (khali)
php artisan config:clear && php artisan config:cache

# ── 7. Ek poora din production clean chalne ke baad hi purana key delete ──
```

**Agar step 6 ke baad kuch decrypt fail ho:** `APP_PREVIOUS_KEYS` wapas daalein, step 5 dobara chalayein. **Purana key tab tak na delete karein jab tak ek poora din clean na chal jaye.**

> **Order (Phase 2 mein jab per-tenant DB passwords `tenants.data` mein aayenge, `06` §5.2.1):** pehle `tenants` re-encrypt, phir payments. Tenant DB password na hone se **poora product down** hai; gateway credential na hone se sirf **naya payment** rukta hai.

### 12.5 Restore drill — har quarter

```bash
# ── 1. Scratch DB banao (production DB par NAHI) ──────────────────────────
sudo mysql -e "CREATE DATABASE khattakbits_restore CHARACTER SET utf8mb4;"
sudo mysql -e "CREATE DATABASE mizaan_t_restore CHARACTER SET utf8mb4;"

# ── 2. Restore ────────────────────────────────────────────────────────────
gunzip -c /var/backups/kb/khattakbits_2026-11-14_0230.sql.gz \
  | sudo mysql khattakbits_restore

gunzip -c /var/backups/kb/tenant_mizaan_t_01HX..._2026-11-14_0230.sql.gz \
  | sudo mysql mizaan_t_restore

# ── 3. Verify — row counts ────────────────────────────────────────────────
sudo mysql khattakbits_restore -e "
  SELECT 'users' t, COUNT(*) n FROM users
  UNION ALL SELECT 'subscriptions', COUNT(*) FROM subscriptions
  UNION ALL SELECT 'invoices', COUNT(*) FROM invoices;
"

# ── 4. Cleanup ────────────────────────────────────────────────────────────
sudo mysql -e "DROP DATABASE khattakbits_restore; DROP DATABASE mizaan_t_restore;"
```

**Restore drill checklist:**

- [ ] Central DB restore hui
- [ ] **Teen** random tenant DBs restore hui (sirf ek nahi)
- [ ] Row counts production se match karte hain (±1%)
- [ ] `secrets_*.tar.gz.enc` decrypt hui (`openssl enc -d -aes-256-cbc -pbkdf2`)
- [ ] **`APP_KEY` password manager se decrypt test pass** (§12.3)
- [ ] Restored app staging par boot hui (migrations + `artisan about`)
- [ ] Time lagaya: **RTO kitna hai?** (target: < 2 ghante)

> **Golden rule:** Jo backup restore test nahi hua, woh backup nahi hai. **Pehle mahine mein ek dafa poora restore** staging par karein.

### 12.6 Off-site — `rclone`

```bash
sudo apt -y install rclone

# ── Backblaze B2 / S3 setup (interactive, ek dafa) ────────────────────────
sudo -u deploy rclone config
# n → name: kb-backup → storage: b2 (ya s3) → credentials → bucket: kb-backups

sudo -u deploy rclone lsd kb-backup:
sudo -u deploy rclone copy /var/backups/kb kb-backup:kb-prod-01 --dry-run
```

**Backup bucket ki security:**

| Rule | Kyun |
|---|---|
| Bucket **private** (public access block on) | Dump mein customer data hai |
| **Object Lock / versioning** on | Ransomware deletion se bachao |
| Separate credentials (sirf `write`, `list`) | Compromised server se purane backups delete na ho sakein |
| **`APP_KEY` is bucket mein NAHI** (§12.3) | Bucket + passphrase = encryption ka faida khatam |
| Lifecycle: 30 din → Glacier/IA, 365 din → delete (payment proofs except) | Cost |

---

## 13. Monitoring

### 13.1 Health check — `/up`

Laravel ka built-in health route `bootstrap/app.php` se register hota hai (`health: '/up'`). Yeh **DB, Redis, queue check nahi karta** — sirf "app boot hui". Is liye do layer chahiye:

```bash
# ── 1. Liveness (app boot hui?) ───────────────────────────────────────────
curl -fsS -o /dev/null -w "%{http_code}\n" https://khattakbits.com/up
# → 200

# ── 2. Readiness (DB + Redis + tenants reachable?) ────────────────────────
#    NEW — ek custom command
php artisan kb:health --json
# → {"db":"ok","redis":"ok","tenants_total":42,"tenants_failed":0,
#    "queue_depth":{"default":3,"webhooks":0,"provisioning":0},
#    "failed_jobs":0,"last_backup":"2026-11-14T02:31:00+05:00"}
```

**Nginx `location = /up`** already access-log exempt hai (§4.3, §4.4).

> **`/up` par rate limit na lagayein** — monitor 1-min interval par hit karta hai, aur `limit_req` ke `burst` ke saath 429 aane lagega jo false alarm dega.

### 13.2 logrotate

```bash
sudo tee /etc/logrotate.d/kb > /dev/null <<'EOF'
/var/www/khattakbits/shared/storage/logs/*.log
/var/www/mizaanbits/shared/storage/logs/*.log
/var/log/nginx/khattakbits.*.log
/var/log/nginx/mizaan.*.log
/var/log/php8.5-fpm-kb*.log
/var/log/kb-backup.log
{
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 deploy www-data
    sharedscripts
    postrotate
        [ -f /run/nginx.pid ] && kill -USR1 $(cat /run/nginx.pid)
    endscript
}
EOF

sudo logrotate -d /etc/logrotate.d/kb     # dry run
```

> **Laravel `LOG_STACK=daily` khud rotate karta hai** (`LOG_DAILY_DAYS=30`). logrotate sirf safety net hai — agar `LOG_STACK=daily` ghalti se `single` ho jaye to disk bharna ruk jaye.

### 13.3 Alerts — kya, kab, kahan

| Layer | Check | Threshold | Tool |
|---|---|---|---|
| **Uptime** | `https://khattakbits.com/up` | 2 consecutive fails / 1 min | BetterStack / UptimeRobot |
| **Uptime (product)** | `https://mizaanbitserp.com/up` | same | same |
| **Readiness** | `kb:health --json` | `db != ok` ya `redis != ok` | cron + webhook |
| **Failed jobs** | `queue:failed` count | **> 0** | `kb:health` |
| **Queue depth** | `redis-cli LLEN queues:provisioning` | > 10 (5 min) | `kb:health` |
| **Stuck provisioning** | `tenants` where `state=failed` | > 0 | `tenants:sweep-stuck` digest (§10.1) |
| **Disk** | `df /` | > 80% | Netdata / node_exporter |
| **MySQL connections** | `Threads_connected` | > 80% of `max_connections` | Netdata |
| **MySQL slow queries** | `slow.log` lines/min | > 10 | `logwatch` |
| **Redis memory** | `used_memory` vs `maxmemory` | > 85% | `kb:health` |
| **SSL expiry** | `openssl -checkend` | < 21 din | cron (§5.3) |
| **Backup** | `last_backup` age | > 26 ghante | `kb:health` |
| **Payment pending** | `payment.verification_pending_count` | > 0 aur `oldest_pending_age_hours > sla_hours` | `payments:sla-alert` (§10.1) |
| **APP_KEY verify** | `security_events` `app_key.verified` age | > 90 din | manual (§12.3) |

**Quick health one-liner (cron, har 5 min):**

```bash
sudo tee /etc/cron.d/kb-health > /dev/null <<'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/5 * * * * deploy curl -fsS https://khattakbits.com/up > /dev/null || echo "KB CP DOWN" | mail -s "ALERT: kb CP" ops@khattakbits.com
*/5 * * * * deploy curl -fsS https://mizaanbitserp.com/up > /dev/null || echo "MIZAAN DOWN" | mail -s "ALERT: mizaan" ops@khattakbits.com
EOF
sudo chmod 644 /etc/cron.d/kb-health
```

**Sentry (optional, recommended):**

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=https://KEY@sentry.io/PROJECT
# .env: SENTRY_LARAVEL_DSN=... SENTRY_TRACES_SAMPLE_RATE=0.1
```

> **`SENTRY_TRACES_SAMPLE_RATE` 0.1 rakhein** — 1.0 par 200 tenants ka traffic quota 2 din mein khatam kar dega.

---

## 14. Production checklist

### 14.1 Security

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` **per app, alag** (§12.3)
- [ ] `APP_PREVIOUS_KEYS=` **khali** (rotation ke ilawa) — `06` §5.2.2 entry 5
- [ ] `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`
- [ ] `SESSION_DOMAIN` **khali** (host-only, §7.3)
- [ ] `CSP_MODE=enforce` — **pehle 1 hafta `report-only`** (§14.2)
- [ ] `ALLOWED_SSO_HOSTS` mein sirf real product domains
- [ ] `php artisan passport:keys` chala hua, private key `chmod 600`, `storage/` ke andar (web root se bahar)
- [ ] `.env` permissions `640`, owner `deploy:www-data`
- [ ] `storage/` aur `bootstrap/cache/` web root se bahar
- [ ] UFW active, sirf 22/80/443 (§1.4)
- [ ] fail2ban active (`fail2ban-client status sshd`)
- [ ] SSH: `PasswordAuthentication no`, `PermitRootLogin no`
- [ ] MySQL `local_infile=0`, `bind-address=127.0.0.1` (§2.5)
- [ ] Redis `requirepass` + `bind 127.0.0.1` + `noeviction` (§2.6)
- [ ] **`mizaan_app` ke paas koi `*.*` privilege nahi** (§3.5)
- [ ] **`GRANT CREATE ON \`prefix\_t\_%\`.*` ka probe chal chuka** (§3.3) — `NOT VERIFIED` item band
- [ ] `payment_gateway_accounts.credentials` encrypt hai (tinker se read kar ke verify) — `06` §5.2.2 entry 6
- [ ] `examples/forbidden` route production se hata hua (bug **B-2**, `01-STATUS-REPORT.md:213`)
- [ ] `/attachments/{id}` IDOR fix deploy hua (bug **B-1**, `01-STATUS-REPORT.md:196`)
- [ ] **`GET` routes read-only** — CI gate `GetRoutesAreReadOnlyTest` chal raha (`05` §7.1, `08` §2.12)

### 14.2 CSP — `report-only` se `enforce` tak

`config/security.php` default **`report-only`** hai (**VERIFIED**). `SecurityHeaders.php:28-31`:

```php
$mode = config('security.csp', 'report-only');
if ($mode !== 'off') {
    $h->set($mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', self::CSP);
}
```

CSP value (**VERIFIED**, `SecurityHeaders.php:10-11`):

```text
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self';
frame-ancestors 'none'; base-uri 'self'; form-action 'self'
```

**Switch procedure:**

```bash
# ── Hafta 1: report-only (default) ────────────────────────────────────────
CSP_MODE=report-only
php artisan config:cache && sudo systemctl reload php8.5-fpm

# ── Har page visit karein, browser console mein violations dekhein ────────
#    Chrome DevTools → Console → filter "Content Security Policy"
#    Ya: chrome://settings/content/all se reports

# ── Hafta 2: violations zero ho to enforce ─────────────────────────────────
CSP_MODE=enforce
php artisan config:cache && sudo systemctl reload php8.5-fpm

# ── VERIFY ────────────────────────────────────────────────────────────────
curl -sI https://khattakbits.com | grep -i content-security
# → content-security-policy: default-src 'self'; ...     ← bina "-Report-Only"
```

**Known CSP tension — `connect-src 'self'`** (bug **S-2**, `01-STATUS-REPORT.md`): product app ka browser CP ko direct call **nahi** kar sakta. Yeh **theek hai** — `08` §2.10 ke saare CP calls **server-side** hain (`KbApiClient`), browser se nahi. Agar kabhi SPA se direct call chahiye ho to `connect-src` mein CP ka origin add karna padega — magar woh `05` §9 ke design decision ke khilaf hai (access token browser mein rakhna).

### 14.3 Payments + `APP_KEY` (06 §5.2.2 entries 4–6)

- [ ] **`APP_KEY` offline copy password manager mein, 2 logon ke paas, 3-mahine test ki hui** (`06` §5.2.2 entry 4, §12.3)
- [ ] `APP_PREVIOUS_KEYS` khali hai (`06` §5.2.2 entry 5)
- [ ] `payment_gateway_accounts.credentials` encrypt hai — verify:
  ```bash
  php artisan tinker --execute="
    \$r = DB::table('payment_gateway_accounts')->first();
    dump(\$r ? (str_starts_with(\$r->credentials, 'eyJ') ? 'PLAINTEXT!' : 'encrypted') : 'no rows');
  "
  # → encrypted
  ```
- [ ] `QUEUE_CONNECTION` `database`/`redis` hai, **`sync` nahi** (`06` §5.2.2 entry 8)
- [ ] `payments:sla-alert` schedule mein hai (`06` §5.2.2 entry 10, §10.1)
- [ ] Payment proofs retention 365 din (`06` §5.4, §12.2)
- [ ] `composer audit` clean — **especially `phpseclib`** (`05` §0.2.1)

### 14.4 Tenancy

- [ ] `database.default` = `central` (**`mysql` nahi** — §3.6)
- [ ] `session.connection` = `central`
- [ ] `queue.connections.database.connection` = `central`
- [ ] `cache.stores.database.connection` = `central`
- [ ] `TENANCY_DB_PREFIX` set (`mizaan_t_`), **prefix badalna data migration hai** (`07` §8.1)
- [ ] `CacheTenancyBootstrapper` **OFF** (`07` §1.5 O2) — `CACHE_STORE=redis` taggable hai, magar `tags()` **kabhi** nahi (`07` §8.2)
- [ ] `tenancy.filesystem.root_override = []`, `suffix_storage_path = false`, `asset_helper_tenancy = false`
- [ ] `tenancy.routes = false` (hum apne routes likhte hain)
- [ ] `permission.cache` per-tenant **key** (`07` §2.3.1) — warna **privilege escalation**
- [ ] `Role`/`Permission` ka `$connection = 'tenant'`, `User` central (`07` D1)
- [ ] Test: tenant context mein `config('session.connection')` non-null (`07` §10.5)
- [ ] Test: `file` store par `Cache::put/get` chalta hai (`07` §8.2 ka trap — `array` taggable hai, `file` nahi)
- [ ] `tenants:migrate` pipeline mein hai (§11.1)
- [ ] `tenants:sweep-stuck --minutes=15` every 5 min (§10.1)

### 14.5 Performance

- [ ] `php artisan optimize` chala hua (config/route/view/event cache)
- [ ] `CACHE_STORE=redis` (bug **B-5** — `SettingsStore::rememberForever` multi-node stale)
- [ ] OPcache on, `validate_timestamps=0`, har deploy par FPM reload (§2.2)
- [ ] Nginx `http2 on` (24.04) ya `listen ... http2` (22.04)
- [ ] `public/build` immutable cache headers (§4.1)
- [ ] `gzip on` / `brotli` (optional)
- [ ] MySQL `innodb_buffer_pool_size` = 50–60% RAM (§2.5)
- [ ] MySQL `table_open_cache` per-tenant DBs ke hisaab se (§2.5)
- [ ] `pm.max_children` RAM ke hisaab se calculate (§2.8)
- [ ] Indexes: `audit_logs(event,id)`, `audit_logs(created_at)`, `users(is_active)`

### 14.6 3D login (WebGL)

- [ ] `three` bundle alag chunk mein — `npm run build` ke baad `public/build/assets/` mein 500 KB+ ka ek chunk
- [ ] **CSP `script-src 'self'`** ke saath Three.js chal raha hai — **verify karein**, kyunke Three.js ke kuch examples `new Function()` use karte hain (CSP mein `unsafe-eval` nahi hai, `SecurityHeaders.php:10`)
- [ ] **WebGL fallback:** Chrome DevTools → Rendering → "Disable WebGL" → login page par **normal HTML form** dikhe, blank screen nahi (`BookScene.vue` ka `emit('unsupported')`)
- [ ] Slow connection: DevTools throttling "Slow 3G" → form **pehle** paint ho, 3D baad mein (do `requestAnimationFrame`)
- [ ] `prefers-reduced-motion` respect ho raha hai
- [ ] Mobile Safari (iOS 16+) par WebGL context loss handle ho
- [ ] `CSP_MODE=enforce` par bhi 3D login chalta hai (§14.2 ke baad test)

### 14.7 Operations

- [ ] `supervisorctl status` — **saat** programs RUNNING (§9)
- [ ] `php artisan schedule:list` dono apps par (§10.4)
- [ ] `schedule:clear-cache` chalaya (stuck locks)
- [ ] Backup cron + **ek poora restore drill** (§12.5)
- [ ] `APP_KEY` offline backup + decrypt test (§12.3)
- [ ] logrotate dry-run pass (§13.2)
- [ ] Uptime monitor `/up` par (CP + product)
- [ ] `kb:health` cron + alerts (§13.3)
- [ ] Sentry DSN set
- [ ] `dep rollback` staging par test kiya
- [ ] `deploy:healthcheck` Deployer mein hai (§11.1)
- [ ] `composer check-platform-reqs` CI **aur** deploy dono mein (§1.8)
- [ ] `composer audit` clean
- [ ] Cert expiry alert (§5.3)

---

## 15. Common failures — quick fixes

### 15.1 Deploy / boot

| Symptom | Wajah | Fix |
|---|---|---|
| `composer install` → `ext-sodium * missing` | §1.8 | `apt install php8.5-sodium && phpenmod sodium` |
| `composer install` → `The "../khattakbits-core" directory does not exist` | `type: path` repo (§6.1) | `vcs` repo + deploy key/`auth.json` |
| `Class "KhattakBits\Core\CoreServiceProvider" not found` | Broken symlink (`path` repo) ya stale cache | `ls -la vendor/khattakbits/` — symlink ho to `vcs` par jayein. `composer dump-autoload -o && php artisan config:clear` |
| `Unable to locate file in Vite manifest` | `public/build` missing/stale | CI artifact upload check (§11.4), `php artisan optimize` |
| `Target class [admin] does not exist` | Stale route cache | `php artisan optimize:clear && php artisan optimize` |
| 500 on every error page | Purana `bootstrap/app.php` (`Response` import missing) | Verify: `use Symfony\Component\HttpFoundation\Response;` |
| Deploy ke baad purana code | `php-fpm:reload` nahi chala (§2.2) | `sudo systemctl reload php8.5-fpm` |
| Deploy ke baad purani config | `config:cache` stale | `php artisan config:clear && php artisan config:cache` |

### 15.2 Tenancy

| Symptom | Wajah | Fix |
|---|---|---|
| **Saare tenants 500** | Tenant migrations central se **pehle** chalin (§8.6) | Order: `migrate` → `tenants:migrate`. `tenants.slug` column check |
| `Table 'mizaan_t_xxx.sessions' doesn't exist` | `session.connection` `null` hai (§3.6) | `SESSION_CONNECTION=central` |
| `Table 'mizaan_t_xxx.jobs' doesn't exist` | `queue.connections.database.connection` `null` | `DB_QUEUE_CONNECTION=central` |
| **Tenant A ke permissions tenant B ko mil rahe hain** | Spatie `PermissionRegistrar::$cacheKey` global hai (`07` §2.3.1) | Per-tenant cache key set karein. **Yeh privilege escalation hai** |
| `Cache store [file] does not support tags` | `CacheTenancyBootstrapper` ON hai | OFF karein (`07` §1.5 O2). **Tests pass honge, prod 500** |
| `/{tenant}/...` route match nahi ho raha | `{tenant}` pehla param nahi (`07` §3.1) | `Route::prefix('{tenant}')` — `prefix` ke andar |
| Tenant slug `login` par app toot raha | Reserved slug list (`07` §3.3) | Slug validation, `04` §5.3 |
| `tenants:migrate --path` kaam nahi kar raha | `config(['tenancy.migration_parameters.--path'])` (§8.6) | `--path=` repeated flag use karein |
| Provisioning `migrating` par phansi | Worker kill / deploy restart (`07` §6.5) | `tenants:sweep-stuck` (§10.1), phir `tenants:provision --retry` |
| Provisioning job dobara chali, `CREATE DATABASE` fail | `--tries=1` nahi tha (§9.1) | Supervisor `--tries=1`, retry `--retry` se |

### 15.3 Session / SSO / cookies

| Symptom | Wajah | Fix |
|---|---|---|
| `419 Page Expired` har form par | `SESSION_DOMAIN` galat ya `SESSION_SECURE_COOKIE=true` + HTTP | Production mein HTTPS lazmi. `SESSION_DOMAIN` **khali** (§7.3) |
| Login ke baad turant logout | `www` par app serve ho raha hai magar cookie apex ke liye (§4.3) | `www` → apex redirect |
| SSO callback `state_session_mismatch` | State doosre browser/session mein bana (`08` §2.8.3 `StateStore::pull()`) | Normal hai — user dobara login kare. Log check karein |
| Login `/` par redirect, product par nahi | `ALLOWED_SSO_HOSTS` khali | `.env` mein domains + `php artisan config:cache` |
| SSO callback par `Column 'password' cannot be null` | `users.password` NOT NULL (§8.4) | Nullable karein (`08` §6.3, core package) |
| `kb.sso.return_to_not_member` | `return_to` ka tenant user ka member nahi (`08` §2.8.6 `CallbackController`) | Sahi hai — picker par redirect |

### 15.4 HTTP / Nginx

| Symptom | Wajah | Fix |
|---|---|---|
| `413 Request Entity Too Large` | `client_max_body_size` chhota | 12M (§4.1), PHP `upload_max_filesize` bhi 12M |
| 502 Bad Gateway | FPM socket path galat ya pool down | `ls -l /run/php/`, `systemctl status php8.5-fpm` |
| `unknown directive "http2"` | Ubuntu 22.04, Nginx 1.18 (§1.2) | `http2 on;` hata kar `listen 443 ssl http2;` |
| Duplicate CSP / cheezein block ho rahi hain | Nginx aur app dono CSP bhej rahe (§4.1) | Nginx se CSP hata dein |
| HSTS header missing | `$request->isSecure()` false (§5.2) | `fastcgi_param HTTPS $https if_not_empty;` |
| `502` sirf deploy ke dauraan | FPM reload + `stopwaitsecs` | Normal (~2–5s). Kam karna ho to FPM pool graceful reload |
| 429 on `/up` | Rate limit `/up` par lagi | `location = /up` ko limit se exempt karein (§4.3) |

### 15.5 Queues / scheduler

| Symptom | Wajah | Fix |
|---|---|---|
| Worker purana code chala raha hai | `queue:restart` nahi chala | Deploy hook mein hai (§11.1); manually `php artisan queue:restart` |
| Jobs queue mein jama, process nahi ho rahe | Supervisor program down ya galat queue name | `supervisorctl status`, `--queue=` naam match karein |
| Job `MaxAttemptsExceededException` | `--tries` kam | §9.1 ke values |
| Provisioning job beech mein mari, state stuck | `--timeout` job timeout se kam | `--timeout=330` > job 300 (§9.1) |
| Schedule command kabhi nahi chalti | `withoutOverlapping` lock stuck | `php artisan schedule:clear-cache` (§10.4) |
| Redis `OOM command not allowed` | `maxmemory-policy noeviction` + full | `maxmemory` barhayein ya queue drain karein. **`allkeys-lru` par na jayein** — jobs evict honge (§2.6) |
| Queue jobs gayab, koi error nahi | `allkeys-lru` eviction | `noeviction` (§2.6) |

### 15.6 Database / grants

| Symptom | Wajah | Fix |
|---|---|---|
| `Access denied for user 'mizaan_app' to database 'mizaan_t_...'` | Grant pattern escape galat (§3.2) | `` `mizaan\_t\_%` `` — backslash lazmi |
| `Access denied ... to database 'mizaan_t_probe'` (provision) | `CREATE DATABASE` ke liye global `CREATE` chahiye (§3.3) | Probe chalayein; `GRANT CREATE ON *.*` (Option A) |
| `Too many connections` | FPM pools + workers zyada | `max_connections=200`, `pm.max_children` kam (§2.8) |
| `Too many open files` | `open_files_limit` / `table_open_cache` | §2.5 ke values |
| `Table 'x' was not locked with LOCK TABLES` | `table_open_cache` chhoti | §2.5 |
| Backup fail `Access denied; you need PROCESS privilege` | Backup user grants | `GRANT PROCESS ON *.*` (§3.2) |
| `caching_sha2_password` auth fail (split server) | MySQL 8 default plugin + no TLS | `--get-server-public-key` ya TLS. Same host par `127.0.0.1` theek hai |

### 15.7 Payments / encryption

| Symptom | Wajah | Fix |
|---|---|---|
| `The payload is invalid` (decrypt) | `APP_KEY` badal gaya, `APP_PREVIOUS_KEYS` khali | Purana key `APP_PREVIOUS_KEYS` mein daalein, `config:cache` (§12.4) |
| Gateway credentials `null` | Decrypt fail, `encrypted:array` silently `null` deta hai | `APP_KEY` check, `payments:reencrypt-secrets` |
| Sab users logout ho gaye | `APP_KEY` rotation (`SESSION_ENCRYPT=true`) | Expected (§12.4) — maintenance window mein karein |
| Invoice email ke link toot gaye | `APP_KEY` rotation + 7-din link | Expected. Support link status page par |

---

## 16. Changelog

### v1 — 14 Nov 2026

Naya document. `docs/02-DEPLOYMENT-GUIDE.md` (12 Nov 2026) ko supersede karta hai.

| # | Kya | Section | Kyun |
|---|---|---|---|
| 1 | **Naya file** `docs/platform/09-DEPLOYMENT-GUIDE.md`, `02` par SUPERSEDED banner | §0.1 | `02` path-based tenancy (14 Nov) se **pehle** likhi gayi thi. Uske §5.3 ka wildcard subdomain block, §6 ka DNS-01 wildcard cert, §3 ka PostgreSQL recommendation, aur ek `kb_app` user with `ALL PRIVILEGES` — sab us decision se mar chuke hain. Structure fit karta tha magar content 80% galat — in-place rewrite se behtar naya file + mapping table |
| 2 | **Wildcard block aur wildcard TLS hata diye** | §4.4, §5.1 | `{tenant}` **path segment** hai, hostname nahi (`07` §3.1). Ek host, ek cert, plain HTTP-01. Koi `*.domain` A record nahi, koi Cloudflare API token nahi, koi `fastcgi_param TENANT_SLUG` nahi (woh host-based resolution wapas laata) |
| 3 | **PostgreSQL → MySQL 8.0**, wajah likhi | §2.5 | `07` §10.2 ka per-product `GRANT` pattern (`mizaan\_t\_%`) MySQL-specific hai — Postgres mein database-name wildcard grants **maujood hi nahi**. Yani MySQL preference nahi, isolation model ka requirement hai |
| 4 | **Teen credentials per product** + exact grants + verify | §3.2–§3.5 | `07` §10.2.1. `_` escape ka farq table, negative test, `GRANT USAGE` ka normal hona |
| 5 | **`GRANT CREATE ON \`prefix\_t\_%\`.*` — `NOT VERIFIED`** + probe script | §3.3 | MySQL 8.0 mein `CREATE DATABASE` ke liye global `CREATE` chahiye (documented). `07` §10.2.3 ne likha magar test nahi kiya. Probe ke dono outcomes ke liye alag instructions, aur Option A ke chaar mitigating controls |
| 6 | **Connection pinning** — `database.default='central'`, session/queue/cache pinning | §3.6 | `07` §2.4. `sessions` table tenant DB mein hoti hi nahi — `session.connection=null` ka bharosa karna silent 500 hai |
| 7 | **Private composer packages** — `type: path` ka CI failure + `vcs` + deploy key + `auth.json` | §6 | Aaj `composer.json` mein `path: ../khattakbits-core` hai (**VERIFIED**) — GitHub Actions par har dafa fail. `symlink: true` "successful" install deta hai magar `vendor/` mein toota symlink |
| 8 | **`ext-sodium` gate** — server prep + CI + `check-platform-reqs` | §1.8, §11.4 | `05` §0.1/§0.2.2. `lcobucci/jwt 5.6` ne sodium polyfill hata diya — hard require. Passport `13.10.*` pin (§6.5) |
| 9 | **`.env` templates** — do poore, `SESSION_DOMAIN` khali | §7.1, §7.2 | Path-based mein host-only cookie — `02` ka poora PSL discussion khatam. CP mein `DB_PROVISION_*` **nahi** (CP tenant DB nahi banata) |
| 10 | **Provisioning queue apni connection par** + `--tries=1` + `--timeout=330` | §9.1 | `06` §5.2.2 entry 8. Auto-retry fresh attempt karta hai → `CREATE DATABASE` fail → orphan. `07` §6.3 ka `--retry` `failed_at_state` se resume karta hai |
| 11 | **`tenants:sweep-stuck --minutes=15` every 5 min** CP par | §10.1 | `07` §6.5. Worker kill par row hamesha `migrating` par phansi thi — koi retry, koi alert nahi |
| 12 | **Subscription tick sirf CP par** | §10.1, §10.2 | `subscriptions` table CP central mein hai. Product par chalane se do state machines |
| 13 | **Backup script: central + SAARE tenant DBs** | §12.2 | `02` §11 sirf 2 hardcoded DBs dump karta tha. Ab `SHOW DATABASES` + prefix match, per-tenant failure isolation, `--single-transaction`, `defaults-extra-file` (`ps` mein password nahi), payment proofs 365 din |
| 14 | **`APP_KEY` offline backup + rotation runbook** | §12.3, §12.4 | `06` §5.2.1/§5.2.2 entries 2, 4, 5, 9. Blast radius Phase 2 mein barhta hai (per-tenant DB passwords `tenants.data` mein), is liye verify cadence 6 → **3 mahine** |
| 15 | **Deployer order** — central → tenants → queue → fpm → health | §11.1, §11.2 | `07` §10.1: tenants pehle chalne par `SlugPathTenantResolver` fail → **saare tenants 500**. `queue:restart` `fpm:reload` se pehle |
| 16 | **GitHub Actions** — `sodium`, private package auth, platform check | §11.4 | `05` §0.2.2. Fine-grained PAT (classic nahi) |
| 17 | **`06` §5.2.2 ki 10 entries** ka mapping table | §0.1 | Contract ka proof — 10/10 address hue |
| 18 | **Alerts table** + `kb:health` (`NEW`) | §13.3 | `06` §5.2.2 entry 10 (payment metrics), `07` §6.5 (stuck), bug B-5 (Redis) |
| 19 | **Checklist 7 sub-sections** (security, CSP, payments/APP_KEY, tenancy, perf, WebGL, ops) | §14 | `02` ka checklist path-based ke liye galat tha. Tenancy sub-section naya hai (pinning, per-tenant permission cache, prefix) |
| 20 | **`NOT VERIFIED` legend + 4 items listed** | §0.5 | Honesty: kya test hua, kya nahi. §3.3 (grants), §5.3 (certbot hook), §11.2 (tenants:migrate rollback), §12.2 (prefix discovery) |
| 21 | **Failures: 7 sub-sections, 40+ rows** | §15 | Tenancy aur Passport failures naye hain — `02` mein nahi the |

**Kya nahi badla:**

- `02` delete nahi hui — banner + mapping (§0.1, §0.2). `06` §5.2.1 us ke `backup.sh` ko cite karta hai
- Server prep ke commands (UFW, fail2ban, swap, SSH) — wohi, sirf `sodium` gate add
- OPcache / FPM pool approach — wohi
- Deployer approach — wohi, hooks badle
- `02` §16 ke compliance items (privacy policy, GDPR) — `09` mein nahi, woh `01-STATUS-REPORT.md` ka P1 hai

**Cross-doc asar:**

| Doc | Kya update karna hai |
|---|---|
| `docs/02-DEPLOYMENT-GUIDE.md` | SUPERSEDED banner (kiya gaya) |
| `06` §5.2.1 line 2275 | `docs/02-DEPLOYMENT-GUIDE.md` §11 → `docs/platform/09-DEPLOYMENT-GUIDE.md` §12.2 |
| `06` §5.2.2 line 2357 | `docs/02-DEPLOYMENT-GUIDE.md` → `docs/platform/09-DEPLOYMENT-GUIDE.md`, mapping table §0.2 |
| `07` §10.2.3 | `NOT VERIFIED` probe ka reference `09` §3.3 par point karein |
| `05` §0.2.2 | "deploy runbook mein add karein" → `09` §1.8 |
| `docs/README.md` | `09` ko index mein add karein |
