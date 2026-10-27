<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        $system = $this->name === 'super-admin';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'users' => (int) ($this->users_count ?? 0),
            'permissions' => (int) ($this->permissions_count ?? 0),
            'system' => $system,
            'type' => $system ? 'Full access' : 'Custom',
        ];
    }
}
