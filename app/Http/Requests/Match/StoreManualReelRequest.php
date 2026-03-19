<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreManualReelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('match'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'minute' => ['required', 'integer', 'min:0'],
            'second' => ['required', 'integer', 'min:0', 'max:59'],
            'player_id' => ['nullable', 'exists:players,id'],
            'request_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'second.max' => 'Los segundos deben estar entre 0 y 59.',
        ];
    }
}
