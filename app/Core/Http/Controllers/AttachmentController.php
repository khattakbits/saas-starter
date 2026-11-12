<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Attachment;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    private function typeConfig(string $type): array
    {
        $types = config('attachments.types', []);
        abort_unless(isset($types[$type]), 404);

        return $types[$type];
    }

    private function allow(Request $request, array $cfg, string $action): void
    {
        abort_unless(array_key_exists($action, $cfg), 403);   // key na ho to deny
        $permission = $cfg[$action];
        if ($permission !== null) {
            abort_unless($request->user()->can($permission), 403);
        }
    }

    /**
     * B-1 fix: type-level permission kaafi nahi thi. `/attachments/{id}` par id
     * enumerate kar ke doosre record ki file download ya delete ho sakti thi.
     * Ab parent record resolve hota hai aur per-record authorization lagti hai:
     *   1. record maujood ho (warna 404),
     *   2. agar type mein `authorize` callback hai to wohi faisla kare,
     *   3. warna `scope => owner` par sirf owner ya `manage` permission wala user.
     */
    private function authorizeRecord(Request $request, array $cfg, int $id, string $action): void
    {
        $model = $cfg['model'] ?? null;

        // Demo/legacy types (model null) sirf local environment mein hote hain.
        if (! $model) {
            return;
        }

        $record = $model::query()->find($id);
        abort_if($record === null, 404);

        $authorize = $cfg['authorize'] ?? null;
        if (is_callable($authorize)) {
            abort_unless($authorize($request->user(), $record, $action) === true, 403);

            return;
        }

        if (($cfg['scope'] ?? 'type') !== 'owner') {
            return;
        }

        $ownerColumn = $cfg['owner_column'] ?? 'created_by';

        if ((int) ($record->{$ownerColumn} ?? 0) === (int) $request->user()->getKey()) {
            return;
        }

        $manage = $cfg['manage'] ?? $cfg['update'] ?? null;
        abort_unless($manage !== null && $request->user()->can($manage), 403);
    }

    private function row(Attachment $a): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'size' => $a->size, 'created_at' => $a->created_at];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['type' => ['required', 'string', 'max:40'], 'id' => ['required', 'integer', 'min:1']]);
        $cfg = $this->typeConfig($data['type']);
        $this->allow($request, $cfg, 'view');
        $this->authorizeRecord($request, $cfg, (int) $data['id'], 'view');

        /** @var Collection<int, Attachment> $attachments */
        $attachments = Attachment::query()
            ->where('attachable_type', $data['type'])
            ->where('attachable_id', $data['id'])
            ->latest('id')
            ->get();

        $files = $attachments->map(fn (Attachment $a): array => $this->row($a));

        return response()->json(['data' => $files]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:'.config('attachments.max_kb'), 'extensions:'.implode(',', config('attachments.extensions'))],
        ]);

        $cfg = $this->typeConfig($data['type']);
        $this->allow($request, $cfg, 'update');
        $this->authorizeRecord($request, $cfg, (int) $data['id'], 'update');

        $file = $request->file('file');
        $this->enforceLimits($request, $data['type'], (int) $data['id'], (int) $file->getSize());

        $path = $file->store('attachments/'.now()->format('Y/m'));   // random naam, private disk

        $attachment = Attachment::create([
            'attachable_type' => $data['type'],
            'attachable_id' => (int) $data['id'],
            'name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->row($attachment)], 201);
    }

    public function show(Request $request, Attachment $attachment)
    {
        $cfg = $this->typeConfig($attachment->attachable_type);
        $this->allow($request, $cfg, 'view');
        $this->authorizeRecord($request, $cfg, (int) $attachment->attachable_id, 'view');

        abort_unless(Storage::exists($attachment->path), 404);

        // Hamesha download (inline nahi): HTML/SVG file browser mein chal kar XSS na kar sake
        return Storage::download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $cfg = $this->typeConfig($attachment->attachable_type);
        $this->allow($request, $cfg, 'delete');
        $this->authorizeRecord($request, $cfg, (int) $attachment->attachable_id, 'delete');

        Storage::delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }

    private function enforceLimits(Request $request, string $type, int $id, int $size): void
    {
        $count = Attachment::query()->where('attachable_type', $type)->where('attachable_id', $id)->count();
        if ($count >= (int) config('attachments.max_files_per_record', 50)) {
            throw ValidationException::withMessages(['file' => 'This record already has the maximum number of attachments.']);
        }

        $usedToday = (int) Attachment::query()->where('uploaded_by', $request->user()->id)
            ->where('created_at', '>=', now()->subDay())
            ->sum('size');
        if ($usedToday + $size > (int) config('attachments.max_mb_per_user_per_day', 200) * 1048576) {
            throw ValidationException::withMessages(['file' => 'Daily upload limit reached. Try again tomorrow.']);
        }
    }
}
