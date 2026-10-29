<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Audit;
use App\Core\Support\SettingsStore;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function show(Request $request, SettingsStore $store)
    {
        return Inertia::render('Settings/System/Show', [
            'settings' => [
                'app_name' => (string) $store->get('app_name', config('app.name')),
                'support_email' => (string) $store->get('support_email', ''),
                'default_page_size' => (string) $store->get('default_page_size', 20),
                'timezone' => (string) $store->get('timezone', 'Asia/Karachi'),
                'recycle_bin_days' => (string) $store->get('recycle_bin_days', 30),
            ],
            'timezones' => collect(DateTimeZone::listIdentifiers())->map(fn ($z) => ['value' => $z, 'label' => $z])->values(),
            'canEdit' => $request->user()->can('settings.system.update'),
        ]);
    }

    public function update(Request $request, SettingsStore $store)
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'min:2', 'max:60'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'default_page_size' => ['required', 'in:20,100,500,2500'],
            'timezone' => ['required', 'timezone:all'],
            'recycle_bin_days' => ['required', 'in:0,7,30,90,365'],
        ]);

        $old = $store->all();
        $changed = array_keys(array_filter(
            $data,
            fn ($v, $k) => (string) ($old[$k] ?? '') !== (string) ($v ?? ''),
            ARRAY_FILTER_USE_BOTH,
        ));

        $store->setMany($data);

        if ($changed) {
            Audit::log('settings.updated', null, 'System settings', [
                'changes' => collect($changed)->mapWithKeys(fn ($k) => [$k => ['from' => $old[$k] ?? null, 'to' => $data[$k]]])->all(),
            ]);
        }

        return back()->with('success', 'Settings saved.');
    }
}
