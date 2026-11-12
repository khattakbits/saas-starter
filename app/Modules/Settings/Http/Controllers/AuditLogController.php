<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Support\DocumentRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Settings\Http\Resources\AuditRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use KhattakBits\Core\Models\AuditLog;
use KhattakBits\Core\Support\TableQuery;
use Spatie\Permission\Models\Role;

class AuditLogController extends Controller
{
    public const EVENTS = [
        'auth.login' => 'Login',
        'auth.logout' => 'Logout',
        'auth.failed' => 'Failed login',
        'auth.password_requested' => 'Password reset requested',
        'auth.password_reset' => 'Password changed',
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.status' => 'User enabled/disabled',
        'role.created' => 'Role created',
        'role.updated' => 'Role updated',
        'role.deleted' => 'Role deleted',
        'settings.updated' => 'Settings updated',
        'doc.created' => 'Document created',
        'doc.updated' => 'Document updated',
        'doc.submitted' => 'Document submitted',
        'doc.cancelled' => 'Document cancelled',
        'doc.amended' => 'Document amended',
        'doc.deleted' => 'Moved to Recycle Bin',
        'doc.restored' => 'Document restored',
        'doc.purged' => 'Permanently deleted',
        'email_account.created' => 'Email account created',
        'email_account.updated' => 'Email account updated',
        'email_account.deleted' => 'Email account deleted',
        'email_account.tested' => 'Email account tested',
        'email_template.updated' => 'Email template updated',
        'email_template.reset' => 'Email template reset',
    ];

    public function index(Request $request)
    {
        $userId = (int) $request->query('user');
        $user = $userId ? User::query()->select('id', 'name')->find($userId) : null;

        return Inertia::render('Settings/AuditLog/Index', [
            'events' => collect(self::EVENTS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'filter' => ['user' => $user ? (string) $user->id : '', 'userLabel' => $user?->name ?? ''],
        ]);
    }

    public function data(Request $request)
    {
        $query = AuditLog::query()->select('id', 'user_name', 'user_email', 'event', 'subject_label', 'ip', 'created_at');
        $isDate = fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'when' => 'id', 'event' => 'event'],
            'default' => 'id',
            'dir' => 'desc',
            'filters' => [
                'event',
                'user' => function ($q, $v) {
                    if (ctype_digit($v)) {
                        $q->where('user_id', (int) $v);
                    }
                },
                'from' => function ($q, $v) use ($isDate) {
                    if ($isDate($v)) {
                        $q->where('created_at', '>=', $v.' 00:00:00');
                    }
                },
                'to' => function ($q, $v) use ($isDate) {
                    if ($isDate($v)) {
                        $q->where('created_at', '<=', $v.' 23:59:59');
                    }
                },
            ],
            'search' => fn ($q, $term) => $q->where('subject_label', 'like', $term.'%'),   // prefix: index use hota hai
        ], AuditRow::class);
    }

    public function users(Request $request)
    {
        $term = addcslashes(trim((string) $request->query('q', '')), '%_\\');

        $rows = User::query()->select('id', 'name', 'email')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $term.'%')->orWhere('email', 'like', $term.'%')))
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name, 'description' => $u->email]);

        return response()->json(['data' => $rows]);
    }

    public function show(Request $request, AuditLog $log)
    {
        $meta = $log->meta ?? [];
        $actor = $request->user();

        $doc = app(DocumentRegistry::class)->get((string) $log->subject_type);

        $docUrl = $doc && $doc['url'] && $actor->can($doc['can'].'.view') && $doc['model']::whereKey($log->subject_id)->exists()
            ? str_replace('{id}', (string) $log->subject_id, $doc['url'])
            : null;

        // Link sirf tab jab dekhne wale ko us page ka haq ho
        $subjectUrl = $docUrl ?? match ($log->subject_type) {
            'user' => $actor->can('settings.users.update') && User::whereKey($log->subject_id)->exists() ? "/settings/users/{$log->subject_id}" : null,
            'role' => $actor->can('settings.roles.update') && Role::whereKey($log->subject_id)->exists() ? "/settings/roles/{$log->subject_id}" : null,
            default => null,
        };

        return Inertia::render('Settings/AuditLog/Show', [
            'log' => [
                'id' => $log->id,
                'when' => $log->created_at?->toIso8601String(),
                'user_id' => $log->user_id,
                'user' => $log->user_name,
                'user_email' => $log->user_email,
                'event' => $log->event,
                'event_label' => self::EVENTS[$log->event] ?? $log->event,
                'subject_type' => $log->subject_type,
                'subject' => $log->subject_label,
                'subject_url' => $subjectUrl,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'changes' => $meta['changes'] ?? null,
                'meta' => collect($meta)->except('changes')->all() ?: null,
            ],
        ]);
    }
}
