<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailAccountRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'from' => $this->from_name ? "{$this->from_name} <{$this->from_email}>" : $this->from_email,
            'from_email' => $this->from_email,
            'host' => $this->host,
            'port' => (int) $this->port,
            'encryption' => strtoupper($this->encryption),
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'status' => $this->is_active ? 'Active' : 'Disabled',
            'last_test' => $this->last_test_ok === null ? 'Never' : ($this->last_test_ok ? 'OK' : 'Failed'),
            'last_test_ok' => $this->last_test_ok,
            'last_tested_at' => $this->last_tested_at?->toIso8601String(),
        ];
    }
}
