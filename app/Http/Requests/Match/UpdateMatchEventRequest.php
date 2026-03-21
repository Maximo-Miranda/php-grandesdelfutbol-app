<?php

namespace App\Http\Requests\Match;

use App\Concerns\ValidatesMatchEventScope;
use App\Enums\MatchEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateMatchEventRequest extends FormRequest
{
    use ValidatesMatchEventScope;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('match'));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'event_type' => ['required', 'string', Rule::enum(MatchEventType::class)],
            'player_id' => ['nullable', 'exists:players,id'],
            'related_player_id' => ['nullable', 'exists:players,id'],
            'team' => ['nullable', 'string', 'in:a,b'],
            'minute' => ['required', 'integer', 'min:0', 'max:200'],
            'second' => ['required', 'integer', 'min:0', 'max:59'],
            'notes' => ['nullable', 'string', 'max:500'],
            'highlighted' => ['sometimes', 'boolean'],
        ];
    }
}
