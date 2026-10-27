<?php

namespace App\Modules\Settings\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('user') ? 'settings.users.update' : 'settings.users.create');
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($target?->id)],
            'password' => [$target ? 'nullable' : 'required', 'confirmed', Password::min(10)],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $actor = $this->user();
                $target = $this->route('user');
                $roles = (array) $this->input('roles', []);
                $active = $this->boolean('is_active');

                if (!$actor->hasRole('super-admin')) {
                    if (in_array('super-admin', $roles, true)) {
                        $v->errors()->add('roles', 'Only a super admin can assign the super-admin role.');
                    }
                    if ($target?->hasRole('super-admin')) {
                        $v->errors()->add('email', 'Only a super admin can edit a super admin account.');
                    }
                }

                if ($target) {
                    if ($target->is($actor) && !$active) {
                        $v->errors()->add('is_active', 'You cannot disable your own account.');
                    }

                    $staysSuper = in_array('super-admin', $roles, true) && $active;
                    if ($target->hasRole('super-admin') && $target->is_active && !$staysSuper) {
                        $others = User::role('super-admin')->where('is_active', true)->where('id', '!=', $target->id)->exists();
                        if (!$others) {
                            $v->errors()->add('roles', 'At least one active super admin is required.');
                        }
                    }
                }
            }
        ];
    }
}