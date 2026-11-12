<?php

namespace App\Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use KhattakBits\Core\Support\Authority;

class RoleRow extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $system = $this->name === 'super-admin';
        $editable = $actor ? Authority::canEditRole($actor, $this->resource) : false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'users' => $this->users_count,
            'permissions' => $this->permissions_count,
            'system' => $system,
            'editable' => $editable,
            'deletable' => ! $system && $editable && $this->users_count === 0,
        ];
    }
}
