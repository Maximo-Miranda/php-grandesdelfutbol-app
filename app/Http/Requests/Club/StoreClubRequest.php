<?php

namespace App\Http\Requests\Club;

use App\Rules\UniqueClubName;
use Illuminate\Foundation\Http\FormRequest;

class StoreClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', new UniqueClubName],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
