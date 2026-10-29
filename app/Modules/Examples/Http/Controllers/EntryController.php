<?php

namespace App\Modules\Examples\Http\Controllers;

use App\Core\Exceptions\DocumentException;
use App\Core\Support\Naming;
use App\Core\Support\TableQuery;
use App\Http\Controllers\Controller;
use App\Modules\Examples\Http\Requests\EntryRequest;
use App\Modules\Examples\Http\Resources\EntryRow;
use App\Modules\Examples\Models\DemoEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EntryController extends Controller
{
    public function index()
    {
        return Inertia::render('Examples/Entries/Index');
    }

    public function data(Request $request)
    {
        $query = DemoEntry::query()->select('id', 'name', 'party', 'amount', 'posting_date', 'docstatus');

        return TableQuery::json($query, $request, [
            'sortable' => [
                'id' => 'id',
                'name' => 'name',
                'party' => 'party',
                'date' => 'posting_date',
                'amount' => 'amount',
                'status' => 'docstatus',
            ],
            'default' => 'id',
            'dir' => 'desc',
            'count' => true,
            'filters' => [
                'status' => fn ($q, $v) => in_array($v, ['0', '1', '2'], true) ? $q->where('docstatus', (int) $v) : null,
            ],
            'search' => fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', $term.'%')->orWhere('party', 'like', $term.'%')),
        ], EntryRow::class);
    }

    public function create()
    {
        return Inertia::render('Examples/Entries/Form', ['entry' => null]);
    }

    public function store(EntryRequest $request)
    {
        $entry = DB::transaction(function () use ($request) {
            $entry = new DemoEntry($request->validated());
            $entry->name = Naming::next('DEMO');
            $entry->save();   // doc.created model ke created event se log hota hai

            return $entry;
        });

        return redirect("/examples/entries/{$entry->id}")->with('success', "{$entry->name} created.");
    }

    public function show(DemoEntry $entry)
    {
        // Draft edit hota hai, baqi sirf parhe jate hain
        return Inertia::render($entry->docstatus === DemoEntry::DRAFT ? 'Examples/Entries/Form' : 'Examples/Entries/Show', [
            'entry' => $this->payload($entry),
        ]);
    }

    public function update(EntryRequest $request, DemoEntry $entry)
    {
        if ($entry->docstatus !== DemoEntry::DRAFT) {
            throw new DocumentException('Only a draft can be edited.');
        }

        $entry->update($request->validated());   // doc.updated model ke updated event se log hota hai

        return back()->with('success', 'Saved.');
    }

    public function submit(DemoEntry $entry)
    {
        $entry->submitDoc();

        return back()->with('success', "{$entry->name} submitted.");
    }

    public function cancel(DemoEntry $entry)
    {
        $entry->cancelDoc();

        return back()->with('success', "{$entry->name} cancelled.");
    }

    public function amend(DemoEntry $entry)
    {
        $copy = $entry->amend();

        return redirect("/examples/entries/{$copy->id}")->with('success', "Amended copy {$copy->name} created as Draft.");
    }

    public function destroy(DemoEntry $entry)
    {
        $entry->delete();

        return redirect('/examples/entries')->with('success', "{$entry->name} moved to the Recycle Bin.");
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:2500'], 'ids.*' => ['integer']])['ids'];
        $moved = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, &$moved, &$skipped) {
            foreach (DemoEntry::query()->whereIn('id', $ids)->get() as $entry) {
                if ($entry->docstatus === DemoEntry::SUBMITTED) {
                    $skipped++;

                    continue;
                }
                $entry->delete();
                $moved++;
            }
        });

        $message = "{$moved} document(s) moved to the Recycle Bin.";
        if ($skipped) {
            $message .= " {$skipped} submitted document(s) skipped. Cancel them first.";
        }

        return back()->with('success', $message);
    }

    private function payload(DemoEntry $e): array
    {
        $from = $e->amended_from ? DemoEntry::withTrashed()->select('id', 'name')->find($e->amended_from) : null;
        $to = DemoEntry::query()->select('id', 'name')->where('amended_from', $e->id)->first();

        return [
            'id' => $e->id,
            'name' => $e->name,
            'party' => $e->party,
            'amount' => (string) $e->amount,
            'posting_date' => $e->posting_date?->format('Y-m-d'),
            'remarks' => $e->remarks,
            'docstatus' => (int) $e->docstatus,
            'updated_at' => $e->updated_at?->toIso8601String(),
            'submitted_at' => $e->submitted_at?->toIso8601String(),
            'cancelled_at' => $e->cancelled_at?->toIso8601String(),
            'amended_from' => $from ? ['id' => $from->id, 'name' => $from->name] : null,
            'amended_to' => $to ? ['id' => $to->id, 'name' => $to->name] : null,
        ];
    }
}
