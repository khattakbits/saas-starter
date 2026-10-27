<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'when' => $this->created_at?->format('d-m-Y H:i:s'),
            'user' => $this->user_name ?? '-',
            'event' => $this->event,
            'subject' => $this->subject_label ?? '',
            'ip' => $this->ip ?? '',
        ];
    }
}