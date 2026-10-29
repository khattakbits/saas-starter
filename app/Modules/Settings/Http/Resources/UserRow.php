<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->roles->pluck('name')->values(),
            'is_active' => (bool) $this->is_active,
            'status' => $this->is_active ? 'Active' : 'Disabled',
            'last_login' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
