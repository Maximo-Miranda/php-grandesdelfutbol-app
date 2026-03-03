<?php

namespace App\Http\Requests\Player;

use App\Enums\PlayerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [\App\Models\Player::class, $this->route('club')]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', Rule::in(PlayerPosition::cases())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'user_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
