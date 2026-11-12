<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use KhattakBits\Core\Support\MailHostGuard;

class EmailAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            $this->route('account') ? 'settings.email_accounts.update' : 'settings.email_accounts.create'
        );
    }

    public function rules(): array
    {
        $target = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('email_accounts', 'name')->ignore($target?->id)],
            'from_email' => ['required', 'email:rfc', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:none,tls,ssl'],
            'username' => ['nullable', 'string', 'max:190'],
            // Password sirf naye account par laazmi hai jab username diya ho; edit par khali chhodne se purana rehta hai
            'password' => [
                Rule::requiredIf(fn () => ! $target && $this->filled('username')),
                'nullable', 'string', 'max:255',
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->has('host')) {
                    return;
                }

                $error = MailHostGuard::error((string) $this->input('host'));
                if ($error !== null) {
                    $v->errors()->add('host', $error);
                }
            },
        ];
    }
}
