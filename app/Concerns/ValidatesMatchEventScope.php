<?php

namespace App\Concerns;

use App\Enums\MatchEventScope;
use App\Enums\MatchEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @mixin FormRequest
 */
trait ValidatesMatchEventScope
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $eventType = MatchEventType::tryFrom($this->input('event_type', ''));

            if (! $eventType) {
                return;
            }

            $hasPlayer = (bool) $this->input('player_id');
            $hasTeam = (bool) $this->input('team');

            match ($eventType->scope()) {
                MatchEventScope::Player => $this->validatePlayerScope($validator, $hasPlayer, $hasTeam),
                MatchEventScope::Team => $this->validateTeamScope($validator, $hasTeam),
                MatchEventScope::Neutral => $this->validateNeutralScope($validator, $eventType, $hasPlayer, $hasTeam),
            };
        });
    }

    private function validatePlayerScope(Validator $validator, bool $hasPlayer, bool $hasTeam): void
    {
        if (! $hasPlayer && ! $hasTeam) {
            $validator->errors()->add('team', 'Se requiere un jugador o un equipo para este evento.');
        }
    }

    private function validateTeamScope(Validator $validator, bool $hasTeam): void
    {
        if (! $hasTeam) {
            $validator->errors()->add('team', 'El equipo es obligatorio para este tipo de evento.');
        }
    }

    private function validateNeutralScope(Validator $validator, MatchEventType $eventType, bool $hasPlayer, bool $hasTeam): void
    {
        if ($hasPlayer) {
            $validator->errors()->add('player_id', 'Los eventos neutrales no deben tener jugador.');
        }
        if ($hasTeam && ! $eventType->allowsOptionalTeam()) {
            $validator->errors()->add('team', 'Los eventos neutrales no deben tener equipo.');
        }
    }
}
