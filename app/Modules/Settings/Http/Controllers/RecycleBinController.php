<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Core\Models\DeletedDocument;
use App\Core\Support\DocumentRegistry;
use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Resources\DeletedRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use KhattakBits\Core\Support\Audit;
use KhattakBits\Core\Support\SettingsStore;
use KhattakBits\Core\Support\TableQuery;

class RecycleBinController extends Controller
{
    public function index(Request $request, DocumentRegistry $docs, SettingsStore $settings)
    {
        $types = collect($docs->all())
            ->filter(fn ($c) => $docs->can($request->user(), $c, 'view'))
            ->map(fn ($c) => ['value' => $c['type'], 'label' => $c['label']])
            ->values();

        return Inertia::render('Settings/RecycleBin/Index', [
            'types' => $types,
            'days' => (int) $settings->get('recycle_bin_days', 30),
        ]);
    }

    public function data(Request $request, DocumentRegistry $docs)
    {
        // Sirf wo doctypes jin ko dekhne ka haq ho
        $visible = collect($docs->all())
            ->filter(fn ($c) => $docs->can($request->user(), $c, 'view'))
            ->keys()->all();

        $query = DeletedDocument::query()->whereIn('doc_type', $visible);

        return TableQuery::json($query, $request, [
            'sortable' => ['id' => 'id', 'deleted' => 'id', 'title' => 'title', 'type' => 'doc_type'],
            'default' => 'deleted',
            'dir' => 'desc',
            'count' => true,
            'filters' => ['type' => fn ($q, $v) => $q->where('doc_type', $v)],
            'search' => fn ($q, $term) => $q->where('title', 'like', $term.'%'),
        ], DeletedRow::class);
    }

    public function restore(Request $request, DocumentRegistry $docs)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:2500'], 'ids.*' => ['integer']])['ids'];
        $user = $request->user();
        $restored = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, $docs, $user, &$restored, &$skipped) {
            foreach (DeletedDocument::query()->whereIn('id', $ids)->get() as $entry) {
                $cfg = $docs->get($entry->doc_type);
                if (! $cfg || ! $docs->can($user, $cfg, 'delete')) {
                    $skipped++;

                    continue;
                }
                $doc = $cfg['model']::withTrashed()->find($entry->doc_id);
                if (! $doc) {
                    $entry->delete();
                    $skipped++;

                    continue;
                }
                $doc->restoreAsDraft();
                $restored++;
            }
        });

        $message = "{$restored} document(s) restored as Draft.";
        if ($skipped) {
            $message .= " {$skipped} skipped.";
        }

        return back()->with('success', $message);
    }

    public function purge(Request $request, DocumentRegistry $docs)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:2500'], 'ids.*' => ['integer']])['ids'];
        $user = $request->user();
        $purged = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, $docs, $user, &$purged, &$skipped) {
            foreach (DeletedDocument::query()->whereIn('id', $ids)->get() as $entry) {
                $cfg = $docs->get($entry->doc_type);
                if (! $cfg || ! $docs->can($user, $cfg, 'delete')) {
                    $skipped++;

                    continue;
                }
                $cfg['model']::withTrashed()->find($entry->doc_id)?->forceDelete();   // files bhi hat jati hain
                $entry->delete();
                Audit::log('doc.purged', null, $entry->title, ['type' => $entry->doc_type]);
                $purged++;
            }
        });

        $message = "{$purged} document(s) permanently deleted.";
        if ($skipped) {
            $message .= " {$skipped} skipped.";
        }

        return back()->with('success', $message);
    }
}
