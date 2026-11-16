<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use KhattakBits\Core\Support\MigrationPaths;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootMigrations();
    }

    /**
     * Host ki apni migrations `database/migrations/{central,tenant}` mein hain.
     *
     * Laravel default `migrate` sirf `database/migrations/*.php` glob karta hai — subfolders
     * **nahi** — is liye inhein explicitly register karna lazmi hai, warna `cache`, `jobs` aur
     * `permissions` tables khamoshi se gayab ho jati hain (docs/platform/03 D2.3).
     *
     * Flag ka faisla wohi hai jo package karta hai: `false` par dono folders (CP ek DB),
     * `true` par sirf central (tenant folders package provider `tenants:migrate` ko deta hai).
     */
    private function bootMigrations(): void
    {
        $tenancyEnabled = (bool) config('khattakbits.tenancy.enabled', false);

        $folders = MigrationPaths::folders(database_path('migrations'));

        foreach (MigrationPaths::forMigrate($folders, $tenancyEnabled) as $path) {
            $this->loadMigrationsFrom($path);
        }
    }
}
