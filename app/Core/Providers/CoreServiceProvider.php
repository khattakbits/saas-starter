<?php

namespace App\Core\Providers;

use App\Core\Console\CreateAdmin;
use App\Core\Console\PruneEmails;
use App\Core\Console\PurgeBin;
use App\Core\Support\DocumentRegistry;
use App\Core\Support\EmailTemplates;
use App\Core\Support\SettingsStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use KhattakBits\Core\Support\ModuleRegistry;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(SettingsStore::class);
        $this->app->singleton(DocumentRegistry::class);
        $this->app->singleton(EmailTemplates::class);
    }

    public function boot(): void
    {
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $migrations = $module['path'].'/Database/Migrations';
            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdmin::class, PurgeBin::class, PruneEmails::class]);
        }

        // N+1: development/tests mein error, production mein sirf log (user ko 500 nahi)
        Model::preventLazyLoading();
        if ($this->app->environment('production')) {
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                logger()->warning("Lazy loading [{$relation}] on [".$model::class.'].');
            });
        }

        Gate::before(fn ($user) => $user->hasRole('super-admin') ? true : null);

        // Do limiter: (email + IP) aur sirf IP. Email badal kar bypass nahi ho sakta
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by('ip|'.$r->ip()),
        ]);

        // Password reset: IP par 5/min aur usi email par 3/min (dono lagte hain)
        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinute(5)->by('ip|'.$r->ip()),
            Limit::perMinute(3)->by('email|'.mb_strtolower(trim((string) $r->input('email')))),
        ]);
    }
}
