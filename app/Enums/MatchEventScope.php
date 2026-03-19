<?php

namespace App\Enums;

enum MatchEventScope: string
{
    case Player = 'player';
    case Team = 'team';
    case Neutral = 'neutral';

    public function label(): string
    {
        return match ($this) {
            self::Player => 'Jugador',
            self::Team => 'Equipo',
            self::Neutral => 'Neutral',
        };
    }
}
