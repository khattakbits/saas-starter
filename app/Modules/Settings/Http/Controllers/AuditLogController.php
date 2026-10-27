<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Models\AuditLog;
use App\Core\Support\TableQuery;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Resources\AuditRow;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public const EVENTS = [
        'auth.login' => 'Login',
        'auth.logout' => 'Logout',
        'auth.failed' => 'Failed login',
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.status' => 'User enabled/disabled',
        'role.created' => 'Role created',
        'role.updated' => 'Role updated',
        'role.deleted' => 'Role deleted',
        'settings.updated' => 'Settings updated',
    ];

    public function index()
    {
        return Inertia::render('Settings/AuditLog/Index', [
            'events' => collect(self::EVENTS)->map(fn($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function data(Request $request)
    {
        $query = AuditLog::query()->select('id', 'user_name', 'event', 'subject_label', 'ip', 'created_at');
        $isDate = fn($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'when' => 'id', 'event' => 'event'],
            'default' => 'id',
            'dir' => 'desc',
            'filters' => [
                'event',
                'from' => function ($q, $v) use ($isDate) {
                    if ($isDate($v))
                        $q->where('created_at', '>=', $v . ' 00:00:00'); },
                'to' => function ($q, $v) use ($isDate) {
                    if ($isDate($v))
                        $q->where('created_at', '<=', $v . ' 23:59:59'); },
            ],
            'search' => fn($q, $term) => $q->where('subject_label', 'like', $term . '%'),   // prefix: index use hota hai
        ], AuditRow::class);
    }
}