<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('role') ? 'settings.roles.update' : 'settings.roles.create');
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
}