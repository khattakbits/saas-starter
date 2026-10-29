<?php

namespace App\Modules\Examples\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntryRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'party' => $this->party,
            'posting_date' => $this->posting_date?->format('Y-m-d'),
            'amount' => (string) $this->amount,
            'docstatus' => (int) $this->docstatus,
            'deletable' => (int) $this->docstatus !== 1,
        ];
    }
}
