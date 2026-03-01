<?php

use App\Enums\MatchEventType;

test('it has the correct values', function () {
    expect(MatchEventType::Goal->value)->toBe('goal')
        ->and(MatchEventType::Assist->value)->toBe('assist')
        ->and(MatchEventType::YellowCard->value)->toBe('yellow_card')
        ->and(MatchEventType::RedCard->value)->toBe('red_card')
        ->and(MatchEventType::PenaltyScored->value)->toBe('penalty_scored')
        ->and(MatchEventType::PenaltyMissed->value)->toBe('penalty_missed')
        ->and(MatchEventType::FreeKick->value)->toBe('free_kick')
        ->and(MatchEventType::Save->value)->toBe('save')
        ->and(MatchEventType::OwnGoal->value)->toBe('own_goal');
});

test('it can be created from value', function () {
    expect(MatchEventType::from('goal'))->toBe(MatchEventType::Goal)
        ->and(MatchEventType::from('own_goal'))->toBe(MatchEventType::OwnGoal);
});

test('tryFrom returns null for invalid value', function () {
    expect(MatchEventType::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(MatchEventType::Goal->label())->toBe('Goal')
        ->and(MatchEventType::YellowCard->label())->toBe('Yellow Card')
        ->and(MatchEventType::PenaltyScored->label())->toBe('Penalty Scored')
        ->and(MatchEventType::OwnGoal->label())->toBe('Own Goal');
});
