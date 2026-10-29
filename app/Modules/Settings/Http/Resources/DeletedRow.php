<?php

namespace App\Modules\Settings\Http\Resources;

use App\Core\Support\DocumentRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeletedRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cfg = app(DocumentRegistry::class)->get($this->doc_type);
        $user = $request->user();
        $mayDelete = $cfg && $user->can($cfg['can'].'.delete');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $cfg['label'] ?? $this->doc_type,
            'deleted_by' => $this->deleted_by_name ?? '-',
            'deleted_at' => $this->created_at?->toIso8601String(),
            'was' => ['Draft', 'Submitted', 'Cancelled'][$this->original_docstatus] ?? '-',
            'can_restore' => $mayDelete && $user->can('settings.recycle_bin.restore'),
            'can_purge' => $mayDelete && $user->can('settings.recycle_bin.purge'),
        ];
    }
}
