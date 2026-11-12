<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\DocumentView;
use App\Core\Support\DocumentRegistry;
use App\Core\Support\DocumentViews;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use KhattakBits\Core\Models\AuditLog;

class DocumentActivityController extends Controller
{
    public const EVENTS = [
        'doc.created',
        'doc.updated',
        'doc.submitted',
        'doc.cancelled',
        'doc.amended',
        'doc.deleted',
        'doc.restored',
    ];

    private const TIMELINE = 50;

    private const VIEWERS = 10;

    /** POST /documents/{type}/{id}/views */
    public function store(Request $request, string $type, int $id)
    {
        $cfg = $this->typeConfig($type);
        $this->authorizeView($request, $cfg);
        $this->ensureExists($cfg, $id);

        DocumentViews::record($type, $id, $request->user());

        return response()->noContent();
    }

    /** GET /documents/{type}/{id}/activity */
    public function show(Request $request, string $type, int $id)
    {
        $cfg = $this->typeConfig($type);
        $this->authorizeView($request, $cfg);
        $this->ensureExists($cfg, $id);

        $before = (int) $request->query('before', 0);

        $logs = AuditLog::query()
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->whereIn('event', self::EVENTS)
            ->when($before > 0, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::TIMELINE)
            ->get(['id', 'event', 'user_id', 'user_name', 'meta', 'created_at']);

        $timeline = $logs->map(fn (AuditLog $log): array => [
            'id' => $log->id,
            'event' => $log->event,
            'title' => $this->title($log),
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'at' => $this->iso($log->created_at),
            'details' => $this->details($log),
        ])->all();

        $viewers = DocumentView::query()
            ->where('doc_type', $type)
            ->where('doc_id', $id)
            ->orderByDesc('last_viewed_at')
            ->orderByDesc('id')
            ->limit(self::VIEWERS)
            ->get(['user_id', 'user_name', 'views', 'last_viewed_at']);

        $totals = DocumentView::query()
            ->where('doc_type', $type)
            ->where('doc_id', $id)
            ->selectRaw('COALESCE(SUM(views), 0) as total, COUNT(*) as people')
            ->first();

        return response()->json([
            'views' => [
                'total' => (int) ($totals->total ?? 0),
                'people' => (int) ($totals->people ?? 0),
                'viewers' => $viewers->map(fn (DocumentView $v): array => [
                    'user_id' => $v->user_id,
                    'name' => $v->user_name,
                    'views' => (int) $v->views,
                    'last_viewed_at' => $this->iso($v->last_viewed_at),
                ])->all(),
            ],
            'timeline' => $timeline,
            'next_before' => count($timeline) === self::TIMELINE ? $timeline[count($timeline) - 1]['id'] : null,
        ]);
    }

    private function typeConfig(string $type): array
    {
        $cfg = app(DocumentRegistry::class)->get($type);
        abort_unless($cfg, 404);

        return $cfg;
    }

    private function authorizeView(Request $request, array $cfg): void
    {
        abort_unless($request->user()?->can($cfg['can'].'.view'), 403);
    }

    /** Soft-deleted (Recycle Bin) document yahan nahi milta */
    private function ensureExists(array $cfg, int $id): void
    {
        abort_unless($cfg['model']::query()->whereKey($id)->exists(), 404);
    }

    private function title(AuditLog $log): string
    {
        $meta = $log->meta ?? [];

        return match ($log->event) {
            'doc.created' => isset($meta['amended_from']) ? 'Created by amending '.$meta['amended_from'] : 'Created as Draft',
            'doc.updated' => 'Edited',
            'doc.submitted' => 'Submitted',
            'doc.cancelled' => 'Cancelled',
            'doc.amended' => isset($meta['amended_to']) ? 'Amended to '.$meta['amended_to'] : 'Amended',
            'doc.deleted' => 'Moved to Recycle Bin',
            'doc.restored' => 'Restored as Draft',
            default => $log->event,
        };
    }

    /** Sirf field ke naam — kabhi purani/nayi value nahi */
    private function details(AuditLog $log): ?array
    {
        if ($log->event !== 'doc.updated') {
            return null;
        }

        $changes = $log->meta['changes'] ?? [];

        return $changes ? array_keys($changes) : null;
    }

    private function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->utc()->toIso8601String();
    }
}
