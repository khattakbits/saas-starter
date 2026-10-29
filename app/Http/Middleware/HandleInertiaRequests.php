<?php

namespace App\Http\Middleware;

use App\Core\Support\MenuBuilder;
use App\Core\Support\SettingsStore;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => fn () => [
                'name' => app(SettingsStore::class)->get('app_name', config('app.name')),
                'pageSize' => (int) app(SettingsStore::class)->get('default_page_size', 20),
                'timezone' => app(SettingsStore::class)->get('timezone', 'Asia/Karachi'),
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'auth' => fn () => ($u = $request->user()) ? [
                'name' => $u->name,
                'email' => $u->email,
                'super' => $u->hasRole('super-admin'),
                'permissions' => $u->getAllPermissions()->pluck('name')->values(),
            ] : null,
            'menu' => fn () => app(MenuBuilder::class)->for($request->user()),
        ];
    }
}
