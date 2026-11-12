<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use KhattakBits\Core\Support\Audit;
use KhattakBits\Core\Support\Mailer;
use KhattakBits\Core\Support\SettingsStore;

class EmailSettingsController extends Controller
{
    public function show(Request $request)
    {
        $settings = Mailer::settings();

        return Inertia::render('Settings/Email/Show', [
            'settings' => [
                'email_enabled' => $settings['email_enabled'],
                'email_reply_to' => $settings['email_reply_to'],
                'email_footer' => $settings['email_footer'],
                'email_daily_limit' => (string) $settings['email_daily_limit'],
                'email_log_days' => (string) $settings['email_log_days'],
            ],
            'mailReady' => Mailer::isReady(),
            'canEdit' => $request->user()->can('settings.email.update'),
        ]);
    }

    public function update(Request $request, SettingsStore $store)
    {
        $data = $request->validate(Mailer::rules());

        $values = [
            'email_enabled' => $request->boolean('email_enabled') ? '1' : '0',
            'email_reply_to' => (string) ($data['email_reply_to'] ?? ''),
            'email_footer' => (string) ($data['email_footer'] ?? ''),
            'email_daily_limit' => (string) (int) $data['email_daily_limit'],
            'email_log_days' => (string) (int) $data['email_log_days'],
        ];

        $old = $store->all();
        $changed = array_keys(array_filter(
            $values,
            fn ($v, $k) => (string) ($old[$k] ?? '') !== $v,
            ARRAY_FILTER_USE_BOTH,
        ));

        $store->setMany($values);
        Mailer::forgetReady();

        if ($changed) {
            Audit::log('settings.updated', null, 'Email settings', [
                'changes' => collect($changed)->mapWithKeys(fn ($k) => [$k => ['from' => $old[$k] ?? null, 'to' => $values[$k]]])->all(),
            ]);
        }

        return back()->with('success', 'Email settings saved.');
    }
}
