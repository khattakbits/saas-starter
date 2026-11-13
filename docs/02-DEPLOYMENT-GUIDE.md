# KhattakBits — Deployment Guide (Self-hosted Ubuntu 24.04)

> ## ⚠️ SUPERSEDED — 14 Nov 2026
>
> **Yeh document authoritative nahi hai. `docs/platform/09-DEPLOYMENT-GUIDE.md` parhein.**
>
> Yeh guide **12 Nov 2026** ko likhi gayi thi — **path-based tenancy ke faisle se pehle** (14 Nov 2026). Us faisle ne is document ke kai hisse ghalaat kar diye:
>
> | Yahan | Masla | Sahi kahan |
> |---|---|---|
> | §5.3 wildcard tenant subdomain block (`server_name ~^(?<tenant>...`) | Path-based mein `{tenant}` **path segment** hai, hostname nahi. Wildcard block ki zaroorat hi nahi | `09` §4.4 |
> | §5.3 `fastcgi_param TENANT_SLUG $tenant` | Slug `{tenant}` route param se aata hai (`07` §3.1). Nginx se inject karna host-based resolution wapas laata hai | `09` §4.4 |
> | §6 DNS-01 wildcard cert + Cloudflare API token | Ek host, ek cert — plain HTTP-01 kaafi hai | `09` §5.1 |
> | §3 PostgreSQL recommendation | `07` §10.2 ka per-product `GRANT` pattern (`mizaan\_t\_%`) MySQL-specific hai. Postgres mein database-name wildcard grants maujood hi nahi | `09` §2.5 |
> | §3 ek `kb_app` user with `ALL PRIVILEGES` | `07` §10.2.1: **teen** credentials per product (`_app`, `_provision`, `_backup`), koi global privilege nahi | `09` §3 |
> | §7.2 `SESSION_DOMAIN=.mizaanbitserp.com` | Path-based mein **host-only** cookie (khali). Poora PSL discussion khatam | `09` §7.3 |
> | §7.2 koi `KB_*` sso-client keys nahi | `08` §2.2 ka `config/kb.php` | `09` §7.2 |
> | §9 koi provisioning queue nahi | Provisioning apni queue, apna connection, `--tries=1` | `09` §9.1 |
> | §11 sirf 2 hardcoded DBs dump karta hai | Central + **saare** tenant DBs | `09` §12.2 |
> | §15 CI mein `sodium` nahi, private packages nahi | Passport `13.10.*` + `ext-sodium` hard require; `type: path` CI mein fail hota hai | `09` §1.8, §6, §11.4 |
> | Koi `APP_KEY` offline backup nahi | `06` §5.2.1 ka blast-radius argument | `09` §12.3 |
>
> **Kyun delete nahi kiya:** `06` §5.2.1 (line 2275) is document ke §11 ke `backup.sh` ko cite karta hai (APP_KEY aur backup bucket ka trust-boundary argument), aur `06` §5.2.2 iske section numbers reference karta hai. `09` §0.2 mein purane → naye section numbers ka mapping table hai.
>
> **Kya abhi bhi yahan se valid hai:** §1 ke server-prep commands (UFW, fail2ban, swap, SSH hardening), §2 ka OPcache/FPM approach, §16 ke compliance items (privacy policy, GDPR) — woh `09` mein dobara nahi likhe gaye.

**Scope (historical):** Ek control plane (`khattakbits.com`) + ek product app (`mizaanbitserp.com`) + wildcard tenant subdomains. Same server, alag FPM pools, alag databases, alag `.env`.

> **Assumption:** Ubuntu 24.04 LTS, root/sudo access, DNS already pointed (A records: `@`, `www`, `*`). Agar Ubuntu 22.04 hai to commands same hain — sirf `ondrej/php` PPA PHP 8.5 support check kar lein.

---

## 0. Server sizing (baseline)

| Tier | Spec | Kya chalega |
|---|---|---|
| Dev/staging | 2 vCPU, 4 GB RAM, 40 GB SSD | 1 CP + 1 product, low traffic |
| Production start | 4 vCPU, 8 GB RAM, 80 GB NVMe | 1 CP + 3 products, ~500 tenants |
| Growth | 8 vCPU, 16 GB, separate DB server | 5+ products, 2000+ tenants |

**3D login note:** Three.js client-side chalta hai — server CPU par asar nahi. Lekin **WebGL bundle ~600 KB** hai; CDN/HTTP2 lazmi hai.

---

## 1. Server prep

```bash
# ── Timezone ────────────────────────────────────────────────────────────────
sudo timedatectl set-timezone Asia/Karachi
timedatectl status

# ── Hostname ────────────────────────────────────────────────────────────────
sudo hostnamectl set-hostname kb-prod-01

# ── System update ───────────────────────────────────────────────────────────
sudo apt update && sudo apt -y upgrade
sudo apt -y install curl wget git unzip zip htop ncdu tree jq \
  software-properties-common ca-certificates gnupg lsb-release ufw fail2ban

# ── Swap (2 GB — 8 GB RAM par bhi rakhein, OOM killer se bachao) ────────────
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
sudo sysctl vm.swappiness=10
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf

# ── Dedicated deploy user ───────────────────────────────────────────────────
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /home/deploy/.ssh && sudo chmod 700 /home/deploy/.ssh
# apni local machine se:
#   ssh-keygen -t ed25519 -C "deploy@kb-prod-01"
#   ssh-copy-id -i ~/.ssh/id_ed25519.pub deploy@<server-ip>
sudo chown -R deploy:deploy /home/deploy/.ssh

# ── SSH hardening ───────────────────────────────────────────────────────────
sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak
sudo tee /etc/ssh/sshd_config.d/99-hardening.conf > /dev/null <<'EOF'
Port 22
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
AllowUsers deploy
EOF
sudo sshd -t && sudo systemctl restart ssh

# ── UFW ─────────────────────────────────────────────────────────────────────
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable
sudo ufw status verbose

# ── fail2ban ────────────────────────────────────────────────────────────────
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOF'
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5
backend  = systemd

[sshd]
enabled = true
port    = 22
EOF
sudo systemctl enable --now fail2ban
sudo fail2ban-client status sshd
```

**Laravel login throttle** app-level hai (`CoreServiceProvider.php:63-75` — 5/min email+IP). fail2ban sirf SSH ke liye hai; Nginx-level rate limit neeche diya hai.

---

## 2. PHP 8.5 + extensions

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

sudo apt -y install \
  php8.5-fpm php8.5-cli php8.5-common php8.5-curl php8.5-mbstring \
  php8.5-xml php8.5-zip php8.5-bcmath php8.5-intl php8.5-gd \
  php8.5-pgsql php8.5-mysql php8.5-redis php8.5-sqlite3 php8.5-opcache

php -v          # PHP 8.5.x
php -m | grep -E 'pdo_pgsql|redis|intl|gd|zip|opcache'
```

> **Project constraint:** `composer.json` → `"php": "^8.3"`. Runtime 8.5 chal raha hai (verified locally: PHP 8.5.12). Agar koi extension 8.5 par missing ho to `php8.4-*` fallback hai — dono chalenge.

**OPcache tuning** — `/etc/php/8.5/mods-available/opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.jit=off
```

`validate_timestamps=0` hai to **har deploy par `systemctl reload php8.5-fpm` lazmi** (Deployer task mein included hai).

**PHP-FPM pool per app** — resource isolation:

```bash
# /etc/php/8.5/fpm/pool.d/kb.conf
sudo tee /etc/php/8.5/fpm/pool.d/kb.conf > /dev/null <<'EOF'
[kb]
user = deploy
group = www-data
listen = /run/php/php8.5-fpm-kb.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
request_terminate_timeout = 60s
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 12M
php_admin_value[post_max_size] = 12M
php_admin_value[error_log] = /var/log/php8.5-fpm-kb.log
php_admin_flag[log_errors] = on
EOF

# /etc/php/8.5/fpm/pool.d/mizaan.conf — same, sirf naam badal kar
sed 's/\[kb\]/[mizaan]/; s/fpm-kb/fpm-mizaan/g; s/max_children = 20/max_children = 12/' \
  /etc/php/8.5/fpm/pool.d/kb.conf | sudo tee /etc/php/8.5/fpm/pool.d/mizaan.conf > /dev/null

sudo systemctl restart php8.5-fpm
ls -l /run/php/
```

> **Note:** `upload_max_filesize=12M` — `config/attachments.php` ka `max_kb => 10240` (10 MB) hai. Nginx `client_max_body_size` bhi 12M rakhna hai warna 413 aayega.

---

## 3. Composer, Node, MySQL/PostgreSQL, Redis

### Composer

```bash
curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
composer --version
```

### Node 22 (Vite 8 ke liye)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt -y install nodejs
node -v && npm -v     # v22.x / 10.x
```

### PostgreSQL 16 (recommended) — kyun, neeche

```bash
sudo apt -y install postgresql-16 postgresql-contrib-16
sudo systemctl enable --now postgresql

sudo -u postgres psql <<'SQL'
CREATE ROLE kb_app LOGIN PASSWORD 'CHANGE_ME_STRONG';
CREATE DATABASE khattakbits OWNER kb_app;
CREATE DATABASE mizaanbits  OWNER kb_app;
GRANT ALL PRIVILEGES ON DATABASE khattakbits TO kb_app;
GRANT ALL PRIVILEGES ON DATABASE mizaanbits  TO kb_app;
SQL
```

**Kyun PostgreSQL (MySQL ke bajaye):**

| | PostgreSQL 16 | MySQL 8 |
|---|---|---|
| JSONB (entitlements, webhook payload) | ✅ indexable | ⚠️ JSON, weaker |
| Row-Level Security (future multi-tenant isolation) | ✅ native | ❌ |
| `CHECK` constraints on enums/status | ✅ | ⚠️ partial |
| Concurrent index creation (zero-downtime) | ✅ `CREATE INDEX CONCURRENTLY` | ❌ |
| Partial indexes | ✅ | ❌ |
| Laravel support | ✅ first-class | ✅ first-class |

Aap ka future multi-tenancy plan hai — Postgres ka **RLS** later "single-DB, shared schema" ko safe banata hai bina application-level scope ke bharose. Isliye Postgres recommend karta hoon. **Agar aap MySQL par hi rehna chahte hain to chalega** — sirf `DB_CONNECTION=mysql` aur RLS ka lalach chhod dein.

### Redis

```bash
sudo apt -y install redis-server
sudo sed -i 's/^# maxmemory .*/maxmemory 512mb/; s/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
sudo sed -i 's/^supervised no/supervised systemd/' /etc/redis/redis.conf
sudo systemctl enable --now redis-server
redis-cli ping     # PONG
```

> **Zaroori:** `config/cache.php` default `database` hai. Production mein `CACHE_STORE=redis` lazmi — warna `SettingsStore::all()` ka `rememberForever` multi-node par stale rahega aur **har request par settings table hit** hogi.

---

## 4. Directory layout

```
/var/www/
├── khattakbits/
│   ├── current -> releases/20261112143000
│   ├── releases/
│   │   ├── 20261112143000/
│   │   └── 20261110120000/
│   ├── shared/
│   │   ├── .env
│   │   ├── storage/            (app, framework, logs)
│   │   └── public/             (uploads agar public disk use ho)
│   └── .dep/                   (Deployer metadata)
└── mizaanbits/
    └── (same structure)
```

```bash
sudo mkdir -p /var/www/khattakbits/{releases,shared/storage/{app,framework/{cache,sessions,views},logs}}
sudo mkdir -p /var/www/mizaanbits/{releases,shared/storage/{app,framework/{cache,sessions,views},logs}}
sudo chown -R deploy:www-data /var/www/khattakbits /var/www/mizaanbits
sudo chmod -R 775 /var/www/khattakbits/shared/storage /var/www/mizaanbits/shared/storage
```

---

## 5. Nginx — multi-domain + wildcard tenants

```bash
sudo apt -y install nginx
sudo rm -f /etc/nginx/sites-enabled/default
```

### 5.1 Snippets

```bash
sudo mkdir -p /etc/nginx/snippets

# ── /etc/nginx/snippets/security.conf ───────────────────────────────────────
sudo tee /etc/nginx/snippets/security.conf > /dev/null <<'EOF'
# NOTE: CSP/HSTS app khud bhejta hai (app/Core/Http/Middleware/SecurityHeaders.php).
# Yahan duplicate na karein warna browser ko do CSP headers milenge aur woh
# intersection enforce karega — debugging nightmare.
add_header X-Content-Type-Options "nosniff" always;
server_tokens off;
EOF

# ── /etc/nginx/snippets/php-kb.conf ─────────────────────────────────────────
sudo tee /etc/nginx/snippets/php-kb.conf > /dev/null <<'EOF'
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass unix:/run/php/php8.5-fpm-kb.sock;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param DOCUMENT_ROOT $realpath_root;
    fastcgi_hide_header X-Powered-By;
    fastcgi_read_timeout 60s;
}
EOF
# php-mizaan.conf — same, sirf socket naam badlein

# ── /etc/nginx/snippets/laravel.conf ────────────────────────────────────────
sudo tee /etc/nginx/snippets/laravel.conf > /dev/null <<'EOF'
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location = /favicon.ico { access_log off; log_not_found off; }
location = /robots.txt  { access_log off; log_not_found off; }
location ~ /\.(?!well-known).* { deny all; }
EOF

# ── /etc/nginx/snippets/ratelimit.conf ──────────────────────────────────────
sudo tee /etc/nginx/snippets/ratelimit.conf > /dev/null <<'EOF'
limit_req_zone $binary_remote_addr zone=login_zone:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=api_zone:10m   rate=120r/m;
limit_req_status 429;
EOF
```

`/etc/nginx/nginx.conf` ke `http { }` block mein include karein:

```nginx
include /etc/nginx/snippets/ratelimit.conf;
```

### 5.2 Control plane — `khattakbits.com`

```bash
sudo tee /etc/nginx/sites-available/khattakbits.conf > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name khattakbits.com www.khattakbits.com;
    root /var/www/khattakbits/current/public;
    index index.php;

    client_max_body_size 12M;
    charset utf-8;

    access_log /var/log/nginx/khattakbits.access.log;
    error_log  /var/log/nginx/khattakbits.error.log;

    include /etc/nginx/snippets/security.conf;
    include /etc/nginx/snippets/laravel.conf;

    # Login endpoint: Nginx-level bhi throttle (app ke 5/min ke upar ek layer)
    location = /login {
        limit_req zone=login_zone burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # OAuth endpoints — Passport routes
    location ~ ^/(oauth|api)/ {
        limit_req zone=api_zone burst=30 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.5-fpm-kb.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
    }

    # Vite build assets — immutable
    location ~* ^/build/.*\.(js|css|woff2|png|jpg|svg|glb)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location = /up { access_log off; try_files $uri /index.php?$query_string; }
}
EOF

sudo ln -s /etc/nginx/sites-available/khattakbits.conf /etc/nginx/sites-enabled/
```

### 5.3 Product app — `mizaanbitserp.com` + wildcard tenants

```bash
sudo tee /etc/nginx/sites-available/mizaanbits.conf > /dev/null <<'EOF'
# ── Apex + www ──────────────────────────────────────────────────────────────
server {
    listen 80;
    listen [::]:80;
    server_name mizaanbitserp.com www.mizaanbitserp.com;
    root /var/www/mizaanbits/current/public;
    index index.php;
    client_max_body_size 12M;

    access_log /var/log/nginx/mizaan.access.log;
    error_log  /var/log/nginx/mizaan.error.log;

    include /etc/nginx/snippets/security.conf;
    include /etc/nginx/snippets/laravel.conf;

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.5-fpm-mizaan.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }
}

# ── Wildcard tenant subdomains: acme.mizaanbitserp.com ─────────────────────
server {
    listen 80;
    listen [::]:80;
    server_name ~^(?<tenant>[a-z0-9][a-z0-9-]{1,61}[a-z0-9])\.mizaanbitserp\.com$;
    root /var/www/mizaanbits/current/public;
    index index.php;
    client_max_body_size 12M;

    access_log /var/log/nginx/mizaan-tenant.access.log;
    error_log  /var/log/nginx/mizaan-tenant.error.log;

    include /etc/nginx/snippets/security.conf;
    include /etc/nginx/snippets/laravel.conf;

    # Tenant slug Laravel ko chala jata hai — app middleware resolve karega
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.5-fpm-mizaan.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param TENANT_SLUG $tenant;
    }
}
EOF

sudo ln -s /etc/nginx/sites-available/mizaanbits.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

> **Order matters:** `www.mizaanbitserp.com` exact match hai, is liye regex block usse nahi chheenga. Nginx exact `server_name` ko regex se pehle match karta hai. `bitcart.com` aur `lawbits.com` ke liye same file copy karein — sirf `server_name`, `root`, aur FPM socket badlein.

### 5.4 Baqi domains (copy pattern)

```bash
for d in bitcart lawbits; do
  sed "s/mizaanbitserp/$d.com/g; s/mizaanbits/$d/g; s/fpm-mizaan/fpm-$d/g; s/\[tenant\]/[tenant]/" \
    /etc/nginx/sites-available/mizaanbits.conf | sudo tee /etc/nginx/sites-available/$d.conf > /dev/null
  sudo ln -sf /etc/nginx/sites-available/$d.conf /etc/nginx/sites-enabled/
done
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. SSL — Let's Encrypt (wildcard via DNS-01)

Wildcard cert **sirf DNS challenge** se milta hai (HTTP-01 se nahi). Cloudflare DNS use kar rahe hain to:

```bash
sudo apt -y install certbot python3-certbot-nginx python3-certbot-dns-cloudflare

# Cloudflare API token (Zone:DNS:Edit permission, sirf yeh zone)
sudo mkdir -p /root/.secrets && sudo chmod 700 /root/.secrets
sudo tee /root/.secrets/cloudflare.ini > /dev/null <<'EOF'
dns_cloudflare_api_token = CF_TOKEN_HERE
EOF
sudo chmod 600 /root/.secrets/cloudflare.ini

# ── Control plane ───────────────────────────────────────────────────────────
sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  --dns-cloudflare-propagation-seconds 60 \
  -d "khattakbits.com" -d "*.khattakbits.com" \
  --email admin@khattakbits.com --agree-tos --no-eff-email

# ── Product app (apex + wildcard tenants) ───────────────────────────────────
sudo certbot certonly --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  --dns-cloudflare-propagation-seconds 60 \
  -d "mizaanbitserp.com" -d "*.mizaanbitserp.com" \
  --email admin@khattakbits.com --agree-tos --no-eff-email
```

Ab har `server` block mein 443 add karein (example: control plane):

```bash
sudo tee /etc/nginx/sites-available/khattakbits.conf > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name khattakbits.com www.khattakbits.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name khattakbits.com www.khattakbits.com;
    root /var/www/khattakbits/current/public;
    index index.php;
    client_max_body_size 12M;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/khattakbits.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/khattakbits.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_stapling on;
    ssl_stapling_verify on;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    access_log /var/log/nginx/khattakbits.access.log;
    error_log  /var/log/nginx/khattakbits.error.log;

    include /etc/nginx/snippets/security.conf;
    include /etc/nginx/snippets/laravel.conf;

    location = /login {
        limit_req zone=login_zone burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ ^/(oauth|api)/ {
        limit_req zone=api_zone burst=30 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.5-fpm-kb.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }
    location ~* ^/build/.*\.(js|css|woff2|png|jpg|svg)$ {
        expires 1y; add_header Cache-Control "public, immutable"; access_log off;
    }
}
EOF

sudo nginx -t && sudo systemctl reload nginx
```

**Auto-renewal** (certbot timer default chalta hai, magar reload hook zaroori):

```bash
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh > /dev/null <<'EOF'
#!/bin/sh
systemctl reload nginx
systemctl reload php8.5-fpm
EOF
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
sudo systemctl enable --now certbot.timer
sudo certbot renew --dry-run
```

> **HSTS warning:** `includeSubDomains` + `preload` mat lagayein jab tak saare subdomains HTTPS-ready na hon. Warna `http://anything.khattakbits.com` permanently block ho jayega aur rollback mushkil hoga.

---

## 7. `.env` templates

### 7.1 KhattakBits (control plane)

```bash
sudo tee /var/www/khattakbits/shared/.env > /dev/null <<'EOF'
APP_NAME="KhattakBits"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://khattakbits.com
APP_TIMEZONE=Asia/Karachi

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=khattakbits
DB_USERNAME=kb_app
DB_PASSWORD=CHANGE_ME_STRONG

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.khattakbits.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_PREFIX=kb_

FILESYSTEM_DISK=local
# Private attachments disk — public web root ke bahar
ATTACHMENTS_DISK=local

# ── Mail ────────────────────────────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@khattakbits.com"
MAIL_FROM_NAME="${APP_NAME}"

# ── Security ────────────────────────────────────────────────────────────────
CSP_MODE=enforce
ALLOWED_SSO_HOSTS="khattakbits.com,mizaanbitserp.com,bitcart.com,lawbits.com"

# ── SSO / OAuth2 (Passport) ─────────────────────────────────────────────────
PASSPORT_PRIVATE_KEY=
PASSPORT_PUBLIC_KEY=
OAUTH_ACCESS_TOKEN_TTL=15
OAUTH_REFRESH_TOKEN_TTL=30
OAUTH_GRACE_PERIOD_DAYS=7

# ── Product registry / webhooks ─────────────────────────────────────────────
KB_CONTROL_PLANE_URL=https://khattakbits.com
KB_WEBHOOK_MAX_ATTEMPTS=7
KB_WEBHOOK_TIMEOUT=10

# ── Attachments ─────────────────────────────────────────────────────────────
ATTACHMENT_MAX_KB=10240
DOCUMENT_VIEW_DEDUPE_MINUTES=10
EOF

sudo chown deploy:www-data /var/www/khattakbits/shared/.env
sudo chmod 640 /var/www/khattakbits/shared/.env
```

### 7.2 Product app (MizaanbitsERP)

```bash
sudo tee /var/www/mizaanbits/shared/.env > /dev/null <<'EOF'
APP_NAME="Mizaanbits ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://mizaanbitserp.com
APP_TIMEZONE=Asia/Karachi

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mizaanbits
DB_USERNAME=kb_app
DB_PASSWORD=CHANGE_ME_STRONG

# ── Shared session for *.khattakbits.com products only ──────────────────────
# mizaanbitserp.com different root domain hai — is liye SESSION_DOMAIN yahan
# .mizaanbitserp.com hoga (tenant subdomains ke liye), KHATTAKBITS ka nahi.
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.mizaanbitserp.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_COOKIE=mb_session

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_PREFIX=mb_

FILESYSTEM_DISK=local
CSP_MODE=enforce

# ── KhattakBits SSO client ──────────────────────────────────────────────────
KB_BASE_URL=https://khattakbits.com
KB_PRODUCT_KEY=mizaanbitserp
KB_CLIENT_ID=kb_live_mizaan_xxxxxxxxxxxxxxxx
KB_CLIENT_SECRET=CHANGE_ME_FROM_ADMIN_PANEL
KB_REDIRECT_URI=https://mizaanbitserp.com/auth/kb/callback
KB_WEBHOOK_SECRET=CHANGE_ME_SIGNING_SECRET
KB_SCOPES="openid profile email subscription entitlements tenant roles"
KB_RESOLVE_CACHE_TTL=60
KB_OFFLINE_GRACE_HOURS=72
KB_ENFORCE_SUBSCRIPTION=true

MAIL_MAILER=smtp
MAIL_HOST=smtp.postmarkapp.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_FROM_ADDRESS="no-reply@mizaanbitserp.com"
MAIL_FROM_NAME="${APP_NAME}"
EOF

sudo chown deploy:www-data /var/www/mizaanbits/shared/.env
sudo chmod 640 /var/www/mizaanbits/shared/.env
```

> **`SESSION_DOMAIN` ka sach:** `.khattakbits.com` sirf un products ke liye kaam karta hai jo `*.khattakbits.com` par hon. `mizaanbitserp.com` **different registrable domain** hai — cookie share **nahi** hogi (browser + PSL). Is liye aap ke 3 methods mein **Method 3 (shared cookie) sirf same-root products ke liye valid hai**. `mizaanbitserp.com` ke liye Method 1 (OAuth2) hi chalega. Yeh vision ka important correction hai.

---

## 8. Pehla deploy (manual, Deployer se pehle)

```bash
# ── Clone ───────────────────────────────────────────────────────────────────
sudo -u deploy git clone git@github.com:YOURUSER/khattakbits.git /var/www/khattakbits/releases/$(date +%Y%m%d%H%M%S)
cd /var/www/khattakbits/releases/$(ls -1t /var/www/khattakbits/releases | head -1)

# ── Shared .env link + storage ──────────────────────────────────────────────
ln -sfn /var/www/khattakbits/shared/.env .env
rm -rf storage
ln -sfn /var/www/khattakbits/shared/storage storage

# ── PHP deps ────────────────────────────────────────────────────────────────
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# ── App key ─────────────────────────────────────────────────────────────────
php artisan key:generate --force

# ── Migrations + seed ───────────────────────────────────────────────────────
php artisan migrate --force
php artisan db:seed --force      # roles/permissions + super-admin

# ── Passport keys (OAuth2) ──────────────────────────────────────────────────
php artisan passport:keys --force
php artisan passport:client --personal --name="KhattakBits Personal"
# Confidential clients per product admin UI se banayenge

# ── Storage link (public disk) ──────────────────────────────────────────────
php artisan storage:link

# ── Frontend build ──────────────────────────────────────────────────────────
npm ci
npm run build

# ── Cache warmup ────────────────────────────────────────────────────────────
php artisan optimize          # config + routes + views + events cache
php artisan filament:optimize 2>/dev/null || true

# ── Permissions ─────────────────────────────────────────────────────────────
php artisan permission:cache-reset

# ── Symlink current ─────────────────────────────────────────────────────────
ln -sfn /var/www/khattakbits/releases/$(basename $PWD) /var/www/khattakbits/current

# ── FPM reload (opcache validate_timestamps=0 hai) ──────────────────────────
sudo systemctl reload php8.5-fpm
```

**Product app** ke liye same steps — sirf `passport:keys` nahi chahiye (woh client hai, server nahi).

> **`npm run build` server par kyun:** Vite 8 + Node 22 chahiye. Agar CI mein build kar ke artifact bhejna chahte hain to `public/build` ko release mein copy karein — server par Node install karne ki zaroorat nahi. **Recommendation: CI mein build.** (Neeche GitHub Actions mein yeh kiya gaya hai.)

---

## 9. Supervisor — queue workers

```bash
sudo apt -y install supervisor

sudo tee /etc/supervisor/conf.d/kb-worker.conf > /dev/null <<'EOF'
[program:kb-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/khattakbits/current/artisan queue:work redis --queue=webhooks,default --sleep=3 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/khattakbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/khattakbits/shared/storage/logs/worker.log
stopwaitsecs=3600
EOF

sudo tee /etc/supervisor/conf.d/mizaan-worker.conf > /dev/null <<'EOF'
[program:mizaan-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mizaanbits/current/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --timeout=90
directory=/var/www/mizaanbits/current
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mizaanbits/shared/storage/logs/worker.log
stopwaitsecs=3600
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

> **`stopwaitsecs=3600`** — `--max-time=3600` ke saath match karta hai. Iske bina deploy par worker forcefully kill hoga aur job adhoora chalega.

> **Deploy ke baad lazmi:** `php artisan queue:restart` — warna workers purana code chalate rahenge.

---

## 10. Scheduler (cron)

```bash
sudo tee /etc/cron.d/khattakbits-scheduler > /dev/null <<'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * deploy cd /var/www/khattakbits/current && php artisan schedule:run >> /dev/null 2>&1
EOF

sudo tee /etc/cron.d/mizaanbits-scheduler > /dev/null <<'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * deploy cd /var/www/mizaanbits/current && php artisan schedule:run >> /dev/null 2>&1
EOF

sudo chmod 644 /etc/cron.d/khattakbits-scheduler /etc/cron.d/mizaanbits-scheduler
```

**Existing schedule** (`routes/console.php:12-13`):

```php
Schedule::command('bin:purge')->dailyAt('03:00');      // recycle bin purge
Schedule::command('emails:prune')->dailyAt('03:30');   // email log prune
```

**Jo add karna hai** (subscription engine ke saath):

```php
// routes/console.php — control plane
Schedule::command('subscriptions:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('subscriptions:send-renewal-reminders')->dailyAt('09:00');
Schedule::command('webhooks:retry')->everyMinute()->withoutOverlapping();
Schedule::command('oauth:prune-tokens')->dailyAt('04:00');
Schedule::command('tenants:sync-entitlements')->hourly();

// product app
Schedule::command('kb:refresh-entitlements')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('kb:retry-failed-webhooks')->everyMinute()->withoutOverlapping();
```

```bash
php artisan schedule:list     # verify
```

---

## 11. Backups

```bash
sudo mkdir -p /var/backups/kb && sudo chown deploy:deploy /var/backups/kb
```

`/home/deploy/backup.sh`:

```bash
#!/usr/bin/env bash
set -Eeuo pipefail

STAMP=$(date +%F_%H%M)
DEST=/var/backups/kb
RETENTION_DAYS=14

# ── Databases ───────────────────────────────────────────────────────────────
PGPASSWORD="$KB_DB_PASS" pg_dump -h 127.0.0.1 -U kb_app -Fc khattakbits \
  > "$DEST/khattakbits_$STAMP.dump"
PGPASSWORD="$KB_DB_PASS" pg_dump -h 127.0.0.1 -U kb_app -Fc mizaanbits \
  > "$DEST/mizaanbits_$STAMP.dump"

# ── Private storage (attachments) ───────────────────────────────────────────
tar -czf "$DEST/storage_$STAMP.tar.gz" \
  -C /var/www/khattakbits/shared storage/app \
  -C /var/www/mizaanbits/shared storage/app

# ── .env files (secrets — encrypted) ────────────────────────────────────────
tar -czf - /var/www/khattakbits/shared/.env /var/www/mizaanbits/shared/.env \
  | openssl enc -aes-256-cbc -pbkdf2 -salt -pass env:BACKUP_PASSPHRASE \
  > "$DEST/env_$STAMP.tar.gz.enc"

# ── Off-site (rclone → Backblaze B2 / S3) ───────────────────────────────────
rclone copy "$DEST" remote:kb-backups/$(hostname) --include "*_$STAMP*" --transfers 4

# ── Retention ───────────────────────────────────────────────────────────────
find "$DEST" -type f -mtime +$RETENTION_DAYS -delete

# ── Verify latest dump is readable ──────────────────────────────────────────
pg_restore --list "$DEST/khattakbits_$STAMP.dump" > /dev/null
echo "[$(date -Is)] backup OK: $STAMP"
```

```bash
chmod +x /home/deploy/backup.sh

sudo tee /etc/cron.d/kb-backup > /dev/null <<'EOF'
SHELL=/bin/bash
30 2 * * * deploy /home/deploy/backup.sh >> /var/log/kb-backup.log 2>&1
EOF
```

**Restore drill (har quarter chalayein):**

```bash
pg_restore -h 127.0.0.1 -U kb_app -d khattakbits_restore --clean --if-exists \
  /var/backups/kb/khattakbits_2026-11-12_0230.dump
```

> **Golden rule:** Jo backup restore test nahi hua, woh backup nahi hai. Pehle mahine mein ek dafa **poora restore** staging par karein.

---

## 12. logrotate

```bash
sudo tee /etc/logrotate.d/khattakbits > /dev/null <<'EOF'
/var/www/khattakbits/shared/storage/logs/*.log
/var/www/mizaanbits/shared/storage/logs/*.log
/var/log/nginx/khattakbits.*.log
/var/log/nginx/mizaan*.log
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

sudo logrotate -d /etc/logrotate.d/khattakbits   # dry run
```

Laravel `LOG_STACK=daily` already daily rotate karta hai (`LOG_DAILY_DAYS=30`) — logrotate sirf Nginx + worker logs ke liye safety net hai.

---

## 13. Monitoring

| Layer | Tool | Setup |
|---|---|---|
| Uptime | UptimeRobot / BetterStack | `https://khattakbits.com/up` (Laravel health route, `bootstrap/app.php:15`) — 1 min interval |
| Errors | Sentry (`sentry/sentry-laravel`) | `SENTRY_LARAVEL_DSN` env; `LOG_LEVEL=warning` |
| Server metrics | Netdata ya node_exporter + Grafana | CPU, RAM, disk, FPM pool saturation |
| Queue depth | `redis-cli LLEN queues:default` | Alert > 1000 |
| Failed jobs | `php artisan queue:failed` | Alert > 0 |
| SSL expiry | certbot timer + BetterStack | 14 din pehle alert |
| DB | `pg_stat_activity` slow query log | `log_min_duration_statement = 500` |

```bash
# Quick health one-liner (cron se har 5 min)
curl -fsS https://khattakbits.com/up || echo "KB DOWN" | mail -s "ALERT" admin@khattakbits.com
```

---

## 14. Zero-downtime deploy (Deployer)

```bash
cd /var/www/khattakbits
composer require --dev deployer/deployer
```

`deploy.php` (control plane — product app ke liye copy karein aur badlein):

```php
<?php

namespace Deployer;

require 'recipe/laravel.php';
require 'contrib/npm.php';

set('application', 'khattakbits');
set('repository', 'git@github.com:YOURUSER/khattakbits.git');
set('keep_releases', 5);
set('git_tty', false);

// ── Shared across releases ──────────────────────────────────────────────────
set('shared_dirs', ['storage']);
set('shared_files', ['.env']);
set('writable_dirs', ['storage', 'bootstrap/cache']);

// ── Build frontend in CI, not on server ─────────────────────────────────────
set('npm_build', false);

host('kb-prod-01')
    ->setHostname('203.0.113.10')
    ->setRemoteUser('deploy')
    ->setDeployPath('/var/www/khattakbits')
    ->setIdentityFile('~/.ssh/id_ed25519')
    ->set('branch', 'main');

// ── Tasks ───────────────────────────────────────────────────────────────────
desc('Upload prebuilt assets from CI artifact');
task('deploy:upload_build', function () {
    if (file_exists('public/build')) {
        upload('public/build/', '{{release_path}}/public/build/');
    }
});

desc('Reload PHP-FPM (opcache.validate_timestamps=0)');
task('php-fpm:reload', function () {
    run('sudo systemctl reload php8.5-fpm');
});

desc('Restart queue workers gracefully');
task('queue:restart', function () {
    run('{{bin/php}} {{release_path}}/artisan queue:restart');
});

desc('Run migrations');
task('artisan:migrate', function () {
    run('{{bin/php}} {{release_path}}/artisan migrate --force --no-interaction');
});

after('deploy:update_code', 'deploy:upload_build');
after('deploy:symlink', 'artisan:migrate');
after('artisan:migrate', 'queue:restart');
after('queue:restart', 'php-fpm:reload');

// ── Health check before finishing ───────────────────────────────────────────
after('deploy:symlink', 'deploy:healthcheck');
task('deploy:healthcheck', function () {
    $code = run('curl -s -o /dev/null -w "%{http_code}" https://khattakbits.com/up');
    if (trim($code) !== '200') {
        throw new \RuntimeException("Health check failed: HTTP $code");
    }
});

// ── On failure: automatic rollback ──────────────────────────────────────────
fail('deploy', 'deploy:rollback');

after('deploy:failed', 'deploy:unlock');
```

**Deploy:**

```bash
dep deploy
```

**Rollback:**

```bash
dep rollback              # previous release par symlink
dep rollback --step=2     # do releases peeche
```

> **Migrations + rollback ka khatra:** `dep rollback` code rollback karta hai, **DB rollback nahi**. Agar migration destructive thi (column drop), rollback se app toot jayegi. **Rule:** production mein sirf **additive migrations** (naye columns nullable, naye tables) — column drop/rename ko 2 releases mein karein (pehle code se hatayein, phir agle release mein column drop).

---

## 15. GitHub Actions → SSH deploy

`.github/workflows/deploy.yml`:

```yaml
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
          extensions: mbstring, pdo_sqlite, pdo_pgsql, redis, intl, gd, zip, bcmath
          coverage: none

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: ~/.composer/cache
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Install Composer deps
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Prepare env
        run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite

      - name: Run tests
        run: php artisan test --parallel

      - name: Pint (code style)
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

      - name: Setup PHP (Deployer)
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'

      - run: composer install --no-dev --prefer-dist --no-interaction

      - name: Configure SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.DEPLOY_SSH_KEY }}" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          ssh-keyscan -H ${{ secrets.DEPLOY_HOST }} >> ~/.ssh/known_hosts

      - name: Deploy
        run: ./vendor/bin/dep deploy production --no-interaction
        env:
          DEPLOY_HOST: ${{ secrets.DEPLOY_HOST }}
```

**Secrets** (GitHub repo → Settings → Secrets):

| Secret | Value |
|---|---|
| `DEPLOY_SSH_KEY` | `deploy` user ki private key (ed25519) |
| `DEPLOY_HOST` | server IP |

**Server side `sudoers`** (deploy user ko FPM reload ki ijazat, bina password):

```bash
sudo tee /etc/sudoers.d/deploy-kb > /dev/null <<'EOF'
deploy ALL=(root) NOPASSWD: /bin/systemctl reload php8.5-fpm, /bin/systemctl reload nginx
EOF
sudo chmod 440 /etc/sudoers.d/deploy-kb
sudo visudo -c
```

---

## 16. Production checklist

### Security

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` set (per app, **alag**)
- [ ] `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- [ ] `CSP_MODE=enforce` — pehle 1 hafta `report-only` chalayein, browser console clean hone ke baad enforce
- [ ] `ALLOWED_SSO_HOSTS` mein sirf real product domains
- [ ] `php artisan passport:keys` chala hua, private key `storage/` ke bahar aur `chmod 600`
- [ ] `.env` permissions `640`, owner `deploy:www-data`
- [ ] `storage/` aur `bootstrap/cache/` web root se bahar
- [ ] UFW + fail2ban active
- [ ] SSH password auth off, root login off
- [ ] `/attachments/{id}` IDOR fix deploy hua (dekhein `01-STATUS-REPORT.md` bug B-1)
- [ ] `examples/forbidden` route production se hata hua (bug B-2)

### 3D login (WebGL) — khaas tawajjo

- [ ] `three` bundle alag chunk mein hai — verify: `npm run build` ke baad `public/build/assets/` mein 500 KB+ ka ek chunk hona chahiye
- [ ] CSP `script-src 'self'` ke saath Three.js chal raha hai (koi `eval` nahi) — **verify karein**, kyunke Three.js ke kuch examples `new Function()` use karte hain
- [ ] **WebGL fallback test:** Chrome DevTools → Rendering → "Disable WebGL" → login page par normal HTML form dikhna chahiye, blank screen nahi (`BookScene.vue:29` ka `emit('unsupported')`)
- [ ] Slow connection test: DevTools throttling "Slow 3G" → form pehle paint ho, 3D baad mein (`BookScene.vue:11-12` — do `requestAnimationFrame`)
- [ ] `prefers-reduced-motion` respect ho raha hai (accessibility)
- [ ] Mobile Safari (iOS 16+) par test — WebGL context loss handle ho

### Performance

- [ ] `php artisan optimize` chala hua (config/route/view cache)
- [ ] `CACHE_STORE=redis` (database nahi)
- [ ] OPcache on, `validate_timestamps=0` + deploy par FPM reload
- [ ] Nginx gzip/brotli on (`brotli` module optional)
- [ ] HTTP/2 enabled
- [ ] `public/build` immutable cache headers
- [ ] DB indexes: `audit_logs(event,id)`, `audit_logs(created_at)`, `users(is_active)` — already maujood

### Operations

- [ ] Supervisor workers running (`supervisorctl status`)
- [ ] Scheduler cron (`php artisan schedule:list` se verify)
- [ ] Backup cron + **ek restore test** done
- [ ] logrotate dry-run pass
- [ ] Uptime monitor `/up` par
- [ ] Sentry DSN set
- [ ] `dep rollback` ek dafa staging par test kiya
- [ ] Health check deploy ke andar (`deploy:healthcheck` task)

### Compliance

- [ ] Privacy policy + ToS pages
- [ ] Cookie consent (EU traffic ho to)
- [ ] Data retention: `email_log_days`, `bin:purge`, audit log retention policy
- [ ] GDPR: user data export + delete (abhi **NOT FOUND** — roadmap P1)

---

## 17. Common failures — quick fixes

| Symptom | Wajah | Fix |
|---|---|---|
| `Unable to locate file in Vite manifest` | `public/build` missing ya stale | `npm run build`, phir `php artisan optimize` |
| 500 on every error page | Purani `bootstrap/app.php` (`Response` import missing) — **already fixed** | Verify line 10: `use Symfony\Component\HttpFoundation\Response;` |
| Login redirect `/` par ja raha hai, product par nahi | `ALLOWED_SSO_HOSTS` khaali | `.env` mein domains add karein + `php artisan config:cache` |
| `419 Page Expired` har form par | `SESSION_DOMAIN` galat ya cookie secure + HTTP | `SESSION_SECURE_COOKIE=false` sirf local; domain check |
| Worker purana code chala raha hai | `queue:restart` nahi chala | Deploy task mein already hai — manually `php artisan queue:restart` |
| 413 Request Entity Too Large | Nginx `client_max_body_size` chhota | 12M set karein (PHP `upload_max_filesize` ke barabar) |
| CSP violation 3D login par | Three.js `new Function()` | `script-src 'self' 'wasm-unsafe-eval'` add karein, ya build config se remove |
| `Too many connections` (Postgres) | FPM pools + workers zyada | `max_connections=200`, pool `max_children` kam karein |
| Attachments 404 | `storage:link` missing ya `ATTACHMENTS_DISK` galat | `php artisan storage:link` |
