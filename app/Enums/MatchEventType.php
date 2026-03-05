<?php

namespace App\Enums;

enum MatchEventType: string
{
    case Goal = 'goal';
    case Assist = 'assist';
    case YellowCard = 'yellow_card';
    case RedCard = 'red_card';
    case PenaltyScored = 'penalty_scored';
    case PenaltyMissed = 'penalty_missed';
    case FreeKick = 'free_kick';
    case Save = 'save';
    case OwnGoal = 'own_goal';
    case Substitution = 'substitution';
    case Injury = 'injury';
    case Foul = 'foul';

    public function label(): string
    {
        return match ($this) {
            self::Goal => 'Gol',
            self::Assist => 'Asistencia',
            self::YellowCard => 'Tarjeta amarilla',
            self::RedCard => 'Tarjeta roja',
            self::PenaltyScored => 'Penal anotado',
            self::PenaltyMissed => 'Penal fallado',
            self::FreeKick => 'Tiro libre',
            self::Save => 'Atajada',
            self::OwnGoal => 'Autogol',
            self::Substitution => 'Cambio',
            self::Injury => 'Lesión',
            self::Foul => 'Falta',
        };
    }
}
