<?php

namespace App\Enums;

enum MatchEventType: string
{
    // Player-scoped
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
    case Handball = 'handball';

    // Team-scoped
    case ShotOnTarget = 'shot_on_target';
    case CornerKick = 'corner_kick';
    case ThrowIn = 'throw_in';
    case Offside = 'offside';
    case TeamFoul = 'team_foul';
    case TeamHandball = 'team_handball';
    case TeamPenalty = 'team_penalty';

    // Neutral-scoped
    case Timeout = 'timeout';
    case BallTouchedReferee = 'ball_touched_referee';
    case StoppageStart = 'stoppage_start';
    case StoppageEnd = 'stoppage_end';
    case WaterBreak = 'water_break';

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
            self::Handball => 'Mano',
            self::ShotOnTarget => 'Tiro al marco',
            self::CornerKick => 'Tiro de esquina',
            self::ThrowIn => 'Saque de banda',
            self::Offside => 'Fuera de juego',
            self::TeamFoul => 'Falta (equipo)',
            self::TeamHandball => 'Mano (equipo)',
            self::TeamPenalty => 'Penal (equipo)',
            self::Timeout => 'Tiempo',
            self::BallTouchedReferee => 'Balón tocó árbitro',
            self::StoppageStart => 'Tiempo detenido',
            self::StoppageEnd => 'Reanudación',
            self::WaterBreak => 'Pausa hidratación',
        };
    }

    public function scope(): MatchEventScope
    {
        return match ($this) {
            self::Goal,
            self::Assist,
            self::YellowCard,
            self::RedCard,
            self::PenaltyScored,
            self::PenaltyMissed,
            self::FreeKick,
            self::Save,
            self::OwnGoal,
            self::Substitution,
            self::Injury,
            self::Foul,
            self::Handball => MatchEventScope::Player,

            self::ShotOnTarget,
            self::CornerKick,
            self::ThrowIn,
            self::Offside,
            self::TeamFoul,
            self::TeamHandball,
            self::TeamPenalty => MatchEventScope::Team,

            self::Timeout,
            self::BallTouchedReferee,
            self::StoppageStart,
            self::StoppageEnd,
            self::WaterBreak => MatchEventScope::Neutral,
        };
    }
}
