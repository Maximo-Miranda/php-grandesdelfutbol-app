<?php

namespace App\Http\Requests\Match;

use App\Enums\MatchEventScope;
use App\Enums\MatchEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMatchEventRequest extends FormRequest
{
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
            'second' => ['sometimes', 'integer', 'min:0', 'max:59'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $eventType = MatchEventType::tryFrom($this->input('event_type', ''));

            if (! $eventType) {
                return;
            }

            $scope = $eventType->scope();
            $hasPlayer = (bool) $this->input('player_id');
            $hasTeam = (bool) $this->input('team');

            match ($scope) {
                MatchEventScope::Player => ! $hasPlayer && ! $hasTeam
                    ? $validator->errors()->add('team', 'Se requiere un jugador o un equipo para este evento.')
                    : null,
                MatchEventScope::Team => ! $hasTeam
                    ? $validator->errors()->add('team', 'El equipo es obligatorio para este tipo de evento.')
                    : null,
                MatchEventScope::Neutral => $this->validateNeutralScope($validator, $hasPlayer, $hasTeam),
            };
        });
    }

    private function validateNeutralScope(Validator $validator, bool $hasPlayer, bool $hasTeam): void
    {
        if ($hasPlayer) {
            $validator->errors()->add('player_id', 'Los eventos neutrales no deben tener jugador.');
        }
        if ($hasTeam) {
            $validator->errors()->add('team', 'Los eventos neutrales no deben tener equipo.');
        }
    }
}
