<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Attachment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    private function authorizeType(Request $request, string $type): void
    {
        $cfg = config("attachments.types.$type");
        abort_if($cfg === null, 404);
        if (!empty($cfg['can'])) {
            abort_unless($request->user()->can($cfg['can']), 403);
        }
    }

    private function row(Attachment $a): array
    {
        return ['id' => $a->id, 'name' => $a->name, 'size' => $a->size, 'created_at' => $a->created_at];
    }

    public function index(Request $request)
    {
        $data = $request->validate(['type' => ['required', 'string', 'max:40'], 'id' => ['required', 'integer', 'min:1']]);
        $this->authorizeType($request, $data['type']);

        $files = Attachment::where('attachable_type', $data['type'])
            ->where('attachable_id', $data['id'])
            ->latest('id')->get()->map(fn($a) => $this->row($a));

        return response()->json(['data' => $files]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'id' => ['required', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:' . config('attachments.max_kb'), 'mimes:' . implode(',', config('attachments.mimes'))],
        ]);
        $type = $request->input('type');
        $this->authorizeType($request, $type);

        $file = $request->file('file');
        $path = $file->store('attachments/' . now()->format('Y/m'));   // random naam, default private disk

        $attachment = Attachment::create([
            'attachable_type' => $type,
            'attachable_id' => (int) $request->input('id'),
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
        $this->authorizeType($request, $attachment->attachable_type);
        abort_unless(Storage::exists($attachment->path), 404);

        // Hamesha download (inline nahi): HTML/SVG file browser mein chal kar XSS na kar sake
        return Storage::download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $this->authorizeType($request, $attachment->attachable_type);
        Storage::delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}