<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\Audit;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use KhattakBits\Core\Models\EmailTemplate;
use KhattakBits\Core\Support\EmailTemplates;
use KhattakBits\Core\Support\Mailer;

class EmailTemplateController extends Controller
{
    public function index(EmailTemplates $templates)
    {
        $overrides = EmailTemplate::query()->get()->keyBy('key');

        $rows = collect($templates->all())
            ->map(fn (array $def, string $key): array => [
                'key' => $key,
                'name' => $def['name'],
                'customized' => $overrides->has($key),
                'is_active' => $overrides->has($key) ? (bool) $overrides->get($key)->is_active : true,
            ])
            ->sortBy('name')
            ->values();

        return Inertia::render('Settings/EmailTemplates/Index', ['templates' => $rows]);
    }

    public function edit(Request $request, string $key, EmailTemplates $templates)
    {
        $def = $templates->get($key);
        abort_unless($def, 404);

        $override = $templates->override($key);

        return Inertia::render('Settings/EmailTemplates/Form', [
            'template' => [
                'key' => $key,
                'name' => $def['name'],
                'subject' => $override?->subject ?? $def['subject'],
                'body' => $override?->body ?? $def['body'],
                'is_active' => $override ? (bool) $override->is_active : true,
                'customized' => (bool) $override,
                'defaults' => ['subject' => $def['subject'], 'body' => $def['body']],
                'placeholders' => collect($def['placeholders'] ?? [])
                    ->map(fn (string $description, string $name): array => [
                        'name' => $name,
                        'description' => $description,
                        'required' => in_array($name, $def['required'] ?? [], true),
                    ])
                    ->values(),
            ],
            'canEdit' => $request->user()->can('settings.email_templates.update'),
        ]);
    }

    public function update(Request $request, string $key, EmailTemplates $templates)
    {
        $def = $templates->get($key);
        abort_unless($def, 404);

        $data = $request->validate([
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $errors = $templates->validate($key, $data['subject'], $data['body']);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($request, $key, $def, $data) {
            $override = EmailTemplate::query()->firstOrNew(['key' => $key]);
            $override->fill([
                'subject' => trim($data['subject']),
                'body' => trim($data['body']),
                'is_active' => $request->boolean('is_active', true),
                'updated_by' => $request->user()->id,
            ])->save();

            Audit::log('email_template.updated', $override, $def['name'], [
                'template' => $key,
                'active' => (bool) $override->is_active,
            ]);
        });

        return back()->with('success', 'Template saved.');
    }

    /** Override delete = default par wapas */
    public function destroy(string $key, EmailTemplates $templates)
    {
        $def = $templates->get($key);
        abort_unless($def, 404);

        $deleted = DB::transaction(function () use ($key, $def) {
            $deleted = EmailTemplate::query()->where('key', $key)->delete();

            if ($deleted) {
                Audit::log('email_template.reset', null, $def['name'], ['template' => $key]);
            }

            return $deleted;
        });

        return back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Template reset to the default.' : 'This template is already using the default.',
        );
    }

    /** Editor ka draft (agar bheja jaye) ya saved version, sample data ke saath */
    public function preview(Request $request, string $key, EmailTemplates $templates)
    {
        $def = $templates->get($key);
        abort_unless($def, 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $samples = $templates->sampleData($key);

        return response()->json(
            isset($data['subject'], $data['body'])
                ? $templates->fillText($data['subject'], $data['body'], $samples)
                : $templates->render($key, $samples)
        );
    }

    /** Template ko sample data ke saath apne aap ko bhej do */
    public function send(Request $request, string $key, EmailTemplates $templates)
    {
        $def = $templates->get($key);
        abort_unless($def, 404);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $samples = $templates->sampleData($key);
        $rendered = isset($data['subject'], $data['body'])
            ? $templates->fillText($data['subject'], $data['body'], $samples)
            : $templates->render($key, $samples);

        $log = Mailer::send($request->user()->email, $rendered['subject'], $rendered['text'], [
            'template_key' => $key,
            'user_id' => $request->user()->id,
        ])->refresh();

        if ($log->status === 'failed') {
            return back()->with('error', $log->error ?: 'The test email could not be sent.');
        }

        return back()->with('success', "Test email sent to {$request->user()->email}.");
    }
}
