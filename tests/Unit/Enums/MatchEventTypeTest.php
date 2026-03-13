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
        ->and(MatchEventType::OwnGoal->value)->toBe('own_goal')
        ->and(MatchEventType::Substitution->value)->toBe('substitution')
        ->and(MatchEventType::Injury->value)->toBe('injury')
        ->and(MatchEventType::Foul->value)->toBe('foul')
        ->and(MatchEventType::Handball->value)->toBe('handball')
        ->and(MatchEventType::Timeout->value)->toBe('timeout');
});

test('it can be created from value', function () {
    expect(MatchEventType::from('goal'))->toBe(MatchEventType::Goal)
        ->and(MatchEventType::from('own_goal'))->toBe(MatchEventType::OwnGoal);
});

test('tryFrom returns null for invalid value', function () {
    expect(MatchEventType::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(MatchEventType::Goal->label())->toBe('Gol')
        ->and(MatchEventType::YellowCard->label())->toBe('Tarjeta amarilla')
        ->and(MatchEventType::PenaltyScored->label())->toBe('Penal anotado')
        ->and(MatchEventType::OwnGoal->label())->toBe('Autogol')
        ->and(MatchEventType::Substitution->label())->toBe('Cambio')
        ->and(MatchEventType::Injury->label())->toBe('Lesión')
        ->and(MatchEventType::Foul->label())->toBe('Falta')
        ->and(MatchEventType::Handball->label())->toBe('Mano')
        ->and(MatchEventType::Timeout->label())->toBe('Tiempo');
});
