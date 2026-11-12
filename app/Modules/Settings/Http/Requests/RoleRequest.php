<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use KhattakBits\Core\Support\Authority;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $role = $this->route('role');

        if ($role) {
            return $actor->can('settings.roles.update') && Authority::canEditRole($actor, $role);
        }

        return $actor->can('settings.roles.create');
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9 _-]*$/',
                Rule::notIn(['super-admin']),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->route('role')?->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $missing = Authority::missing($this->user(), (array) $this->input('permissions', []));
                if ($missing) {
                    $v->errors()->add('permissions', 'You cannot grant permissions you do not hold: '.implode(', ', $missing).'.');
                }
            },
        ];
    }
}
