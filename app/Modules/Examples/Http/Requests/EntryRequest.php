<?php

namespace App\Modules\Examples\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('entry') ? 'examples.entries.update' : 'examples.entries.create');
    }

    public function rules(): array
    {
        return [
            'party' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999999'],
            'posting_date' => ['required', 'date_format:Y-m-d'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
