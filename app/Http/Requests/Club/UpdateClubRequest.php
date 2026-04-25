<?php

namespace App\Http\Requests\Club;

use App\Rules\UniqueClubName;
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
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueClubName($this->route('club')?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
