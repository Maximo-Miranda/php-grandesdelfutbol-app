<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('club'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_approval' => ['boolean'],
            'is_invite_active' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
