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

    public function label(): string
    {
        return match ($this) {
            self::Goal => 'Goal',
            self::Assist => 'Assist',
            self::YellowCard => 'Yellow Card',
            self::RedCard => 'Red Card',
            self::PenaltyScored => 'Penalty Scored',
            self::PenaltyMissed => 'Penalty Missed',
            self::FreeKick => 'Free Kick',
            self::Save => 'Save',
            self::OwnGoal => 'Own Goal',
        };
    }
}
