<?php

namespace App\Modules\Settings\Http\Requests;

use App\Core\Support\Authority;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if ($target) {
            return $actor->can('settings.users.update') && Authority::canManageUser($actor, $target);
        }

        return $actor->can('settings.users.create');
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($target?->id)],
            'password' => [$target ? 'nullable' : 'required', 'confirmed', Password::min(10)],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
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

                // Partial update mein jo bheja nahi gaya wo maujooda rehta hai
                $roles = $this->has('roles') ? (array) $this->input('roles') : ($target?->getRoleNames()->all() ?? []);
                $active = $this->has('is_active') ? $this->boolean('is_active') : (bool) ($target?->is_active ?? true);

                if (! Authority::isSuper($actor) && in_array('super-admin', $roles, true)) {
                    $v->errors()->add('roles', 'Only a super admin can assign the super-admin role.');
                } elseif (! Authority::canAssignRoles($actor, $roles)) {
                    $v->errors()->add('roles', 'You can only assign roles whose permissions you already hold.');
                }

                if ($target) {
                    if ($target->is($actor) && ! $active) {
                        $v->errors()->add('is_active', 'You cannot disable your own account.');
                    }

                    $staysSuper = in_array('super-admin', $roles, true) && $active;
                    if ($target->hasRole('super-admin') && $target->is_active && ! $staysSuper) {
                        $others = User::role('super-admin')->where('is_active', true)->where('id', '!=', $target->id)->exists();
                        if (! $others) {
                            $v->errors()->add('roles', 'At least one active super admin is required.');
                        }
                    }
                }
            },
        ];
    }
}
