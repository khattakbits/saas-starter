<?php

namespace App\Core\Providers;

use App\Core\Console\CreateAdmin;
use App\Core\Support\ModuleRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
        $this->app->singleton(\App\Core\Support\SettingsStore::class);
    }

    public function boot(): void
    {
        // Har module ke migrations khud load
        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            $migrations = $module['path'] . '/Database/Migrations';
            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CreateAdmin::class]);
        }

        // N+1 queries development mein foran error dengi
        Model::preventLazyLoading(!$this->app->environment('production'));

        // super-admin role ko har cheez ka haq
        Gate::before(fn($user) => $user->hasRole('super-admin') ? true : null);

        RateLimiter::for('login', fn(Request $r) => Limit::perMinute(5)
            ->by(strtolower((string) $r->input('email')) . '|' . $r->ip()));
    }
}