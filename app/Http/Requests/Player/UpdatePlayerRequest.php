<?php

namespace App\Http\Requests\Player;

use App\Enums\PlayerPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('player'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $player = $this->route('player');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', Rule::in(PlayerPosition::cases())],
            'jersey_number' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
        if ($player->club->isAdminOrOwner($this->user())) {
            $rules['is_active'] = ['boolean'];
            $rules['user_id'] = ['nullable', 'exists:users,id'];
        }

        return $rules;
    }
}
