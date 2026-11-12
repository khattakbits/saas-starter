<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailLogRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'when' => $this->created_at?->toIso8601String(),
            'to' => $this->to_email,
            'subject' => $this->subject,
            'template' => $this->template_key,
            'status' => $this->status,
            'account' => $this->account?->name,
            'attempts' => (int) $this->attempts,
        ];
    }
}
