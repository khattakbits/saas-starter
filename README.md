# KhattakBits — product skeleton

Thin Laravel application. Ismein product ka apna code hai; platform ka saara
shared kernel aur UI do private packages se aata hai.

```
khattakbits/
├── saas-starter/          ← yeh repo: product skeleton
├── khattakbits-core/      ← PHP kernel (khattakbits/core)
└── khattakbits-ui/        ← Vue layer (@khattakbits/ui)
```

## Skeleton mein kya bacha hai

| Path | Kya hai |
|---|---|
| `app/Models/User.php` | thin subclass of `KhattakBits\Core\Models\User` — product yahan apni relations/traits add karta hai |
| `app/Http/Controllers/Controller.php` | product base controller |
| `app/Http/Middleware/HandleInertiaRequests.php` | Inertia shared props (app name, menu, permissions) |
| `app/Modules/**` | product modules (`Examples`, `Settings`) — har module apni routes, migrations, controllers, Vue pages laata hai |
| `app/Providers/AppServiceProvider.php` | product-level bindings |
| `routes/web.php` | sirf module route loop; core routes package se aate hain |
| `config/*.php` | published copies of the core config |
| `resources/js/Pages/**` | product Inertia pages |
| `resources/js/app.js` | Inertia entry point |

`app/Core` **maujood nahi** — wo poora `khattakbits/core` mein chala gaya hai
(`docs/platform/03-EXTRACTION-PLAN.md`).

## Packages

### `khattakbits/core`

Composer path repository se aata hai (`composer.json` → `repositories`), dev
mein symlink, production mein `vcs` repository. Andar: user model, settings,
mailer, audit, documents (attachments / recycle bin / activity), RBAC
(`Authority`, `PermissionCatalog`), module registry, core routes, config
defaults aur migrations.

Auto-discovered hai (`extra.laravel.providers`), is liye
`bootstrap/providers.php` mein register **nahi** karna — warna double boot hoga.

### `@khattakbits/ui`

`package.json` mein `file:../khattakbits-ui`. Source-consumed — koi build step
nahi, host ka Vite seedha compile karta hai. Host imports `@core/...` likhte
hain; `vite.config.js` ka alias usse package par le jata hai.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan admin:create you@example.com --name="Super Admin"
npm run dev
```

`DatabaseSeeder` sirf permissions/roles banata hai. Admin user `admin:create`
se banta hai (password prompt, kam az kam 10 characters).

## Zaroori conventions

- **Cross-DB foreign keys nahi.** Users central hain, baqi tables tenant-level.
  `user_id` par FK nahi lagti — snapshot columns (`user_name`, `user_email`,
  `deleted_by_name`) audit aur recycle-bin rows ko delete ke baad bhi readable
  rakhte hain.
- **Users delete nahi hote**, deactivate hote hain (`blocked_at` / `is_active`).
  `User::booted()` ka `deleted` hook sirf last-resort safeguard hai.
- **Config publish kiya hua hi asal hai.** `mergeConfigFrom()` config cache ke
  saath kaam nahi karta, is liye product apni `config/*.php` copies rakhta hai.
  `php artisan vendor:publish --tag=khattakbits-config` se refresh karein.
- **Naya doctype** add karna ho to `config/attachments.php` ke `types` mein entry
  dalein aur `config/documents.php` + `IsDocument` concern follow karein.

## Tests

```bash
php artisan test      # 117 passed (588 assertions)
npm run build         # Vite
```

CI: `.github/workflows/tests.yml` — PHP 8.3 aur 8.5 matrix, sqlite `:memory:`.

## Docs

- `docs/platform/02-ARCHITECTURE-DECISIONS.md` — approved decisions, package
  split, tenancy design
- `docs/platform/03-EXTRACTION-PLAN.md` — extraction plan aur jo actually hua
