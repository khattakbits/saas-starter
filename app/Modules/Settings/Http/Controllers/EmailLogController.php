<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Models\EmailLog;
use App\Core\Support\TableQuery;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Resources\EmailLogRow;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailLogController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/EmailLog/Index', [
            'templates' => EmailLog::query()
                ->whereNotNull('template_key')
                ->distinct()
                ->orderBy('template_key')
                ->pluck('template_key'),
        ]);
    }

    public function data(Request $request)
    {
        $query = EmailLog::query()
            ->with('account:id,name')
            ->select('id', 'email_account_id', 'to_email', 'subject', 'template_key', 'status', 'attempts', 'created_at', 'sent_at');

        $isDate = fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'when' => 'created_at', 'to' => 'to_email', 'status' => 'status'],
            'default' => 'when',
            'dir' => 'desc',
            'count' => true,
            'filters' => [
                'status' => function ($q, $v) {
                    if (in_array($v, ['queued', 'sent', 'failed'], true)) {
                        $q->where('status', $v);
                    }
                },
                'template' => fn ($q, $v) => $q->where('template_key', $v),
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
            'search' => fn ($q, $term) => $q->where('to_email', 'like', $term.'%'),   // prefix: index use hota hai
        ], EmailLogRow::class);
    }

    public function show(EmailLog $log)
    {
        $log->load('account:id,name');   // explicit: lazy loading band hai

        return Inertia::render('Settings/EmailLog/Show', [
            'log' => [
                'id' => $log->id,
                'when' => $log->created_at?->toIso8601String(),
                'to' => $log->to_email,
                'subject' => $log->subject,
                'template' => $log->template_key,
                'status' => $log->status,
                'account' => $log->account?->name,
                'attempts' => (int) $log->attempts,
                'queued_at' => $log->queued_at?->toIso8601String(),
                'sent_at' => $log->sent_at?->toIso8601String(),
                'error' => $log->error,
            ],
        ]);
    }
}
