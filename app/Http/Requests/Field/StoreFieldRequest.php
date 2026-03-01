<?php

namespace App\Http\Requests\Field;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('venue'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string', 'in:5v5,6v6,7v7,8v8,9v9,10v10,11v11'],
            'surface_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
