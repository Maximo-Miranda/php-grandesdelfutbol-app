<?php

namespace App\Http\Requests\Match;

use App\Concerns\ValidatesReelTime;
use Illuminate\Foundation\Http\FormRequest;

class StoreReelRequestRequest extends FormRequest
{
    use ValidatesReelTime;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'minute' => ['required', 'integer', 'min:0'],
            'second' => ['required', 'integer', 'min:0', 'max:59'],
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
