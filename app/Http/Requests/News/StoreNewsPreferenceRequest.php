<?php

namespace App\Http\Requests\News;

use Illuminate\Foundation\Http\FormRequest;

class StoreNewsPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'competitions' => ['nullable', 'array'],
            'competitions.*' => ['string', 'max:100'],
            'teams' => ['nullable', 'array'],
            'teams.*' => ['string', 'max:100'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string', 'max:100'],
            'free_text_input' => ['nullable', 'string', 'max:500'],
        ];
    }
}
