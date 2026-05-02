<?php

use App\Enums\MatchEventScope;
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
        ->and(MatchEventType::ShotOnTarget->value)->toBe('shot_on_target')
        ->and(MatchEventType::CornerKick->value)->toBe('corner_kick')
        ->and(MatchEventType::ThrowIn->value)->toBe('throw_in')
        ->and(MatchEventType::Offside->value)->toBe('offside')
        ->and(MatchEventType::TeamFoul->value)->toBe('team_foul')
        ->and(MatchEventType::TeamHandball->value)->toBe('team_handball')
        ->and(MatchEventType::TeamPenalty->value)->toBe('team_penalty')
        ->and(MatchEventType::Timeout->value)->toBe('timeout')
        ->and(MatchEventType::BallTouchedReferee->value)->toBe('ball_touched_referee')
        ->and(MatchEventType::StoppageStart->value)->toBe('stoppage_start')
        ->and(MatchEventType::StoppageEnd->value)->toBe('stoppage_end')
        ->and(MatchEventType::WaterBreak->value)->toBe('water_break')
        ->and(MatchEventType::BlueCard->value)->toBe('blue_card')
        ->and(MatchEventType::MatchStart->value)->toBe('match_start')
        ->and(MatchEventType::FirstHalfEnd->value)->toBe('first_half_end')
        ->and(MatchEventType::SecondHalfStart->value)->toBe('second_half_start')
        ->and(MatchEventType::MatchEnd->value)->toBe('match_end');
});

test('it can be created from value', function () {
    expect(MatchEventType::from('goal'))->toBe(MatchEventType::Goal)
        ->and(MatchEventType::from('own_goal'))->toBe(MatchEventType::OwnGoal)
        ->and(MatchEventType::from('shot_on_target'))->toBe(MatchEventType::ShotOnTarget)
        ->and(MatchEventType::from('water_break'))->toBe(MatchEventType::WaterBreak);
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
        ->and(MatchEventType::ShotOnTarget->label())->toBe('Tiro al marco')
        ->and(MatchEventType::CornerKick->label())->toBe('Tiro de esquina')
        ->and(MatchEventType::ThrowIn->label())->toBe('Saque de banda')
        ->and(MatchEventType::Offside->label())->toBe('Fuera de juego')
        ->and(MatchEventType::TeamFoul->label())->toBe('Falta (equipo)')
        ->and(MatchEventType::TeamHandball->label())->toBe('Mano (equipo)')
        ->and(MatchEventType::TeamPenalty->label())->toBe('Penal (equipo)')
        ->and(MatchEventType::Timeout->label())->toBe('Tiempo')
        ->and(MatchEventType::BallTouchedReferee->label())->toBe('Balón tocó árbitro')
        ->and(MatchEventType::StoppageStart->label())->toBe('Juego detenido')
        ->and(MatchEventType::StoppageEnd->label())->toBe('Juego reanudado')
        ->and(MatchEventType::WaterBreak->label())->toBe('Pausa hidratación')
        ->and(MatchEventType::BlueCard->label())->toBe('Tarjeta azul')
        ->and(MatchEventType::MatchStart->label())->toBe('Inicio del partido')
        ->and(MatchEventType::FirstHalfEnd->label())->toBe('Fin del primer tiempo')
        ->and(MatchEventType::SecondHalfStart->label())->toBe('Inicio del segundo tiempo')
        ->and(MatchEventType::MatchEnd->label())->toBe('Fin del partido');
});

test('all event types have a label', function () {
    foreach (MatchEventType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

test('all event types have a scope', function () {
    foreach (MatchEventType::cases() as $case) {
        expect($case->scope())->toBeInstanceOf(MatchEventScope::class);
    }
});

test('player-scoped events have correct scope', function () {
    $playerEvents = [
        MatchEventType::Goal, MatchEventType::Assist, MatchEventType::YellowCard,
        MatchEventType::RedCard, MatchEventType::BlueCard,
        MatchEventType::PenaltyScored, MatchEventType::PenaltyMissed,
        MatchEventType::FreeKick, MatchEventType::Save, MatchEventType::OwnGoal,
        MatchEventType::Substitution, MatchEventType::Injury, MatchEventType::Foul,
        MatchEventType::Handball,
    ];

    foreach ($playerEvents as $event) {
        expect($event->scope())->toBe(MatchEventScope::Player);
    }
});

test('team-scoped events have correct scope', function () {
    $teamEvents = [
        MatchEventType::ShotOnTarget, MatchEventType::CornerKick, MatchEventType::ThrowIn,
        MatchEventType::Offside, MatchEventType::TeamFoul, MatchEventType::TeamHandball,
        MatchEventType::TeamPenalty,
    ];

    foreach ($teamEvents as $event) {
        expect($event->scope())->toBe(MatchEventScope::Team);
    }
});

test('neutral-scoped events have correct scope', function () {
    $neutralEvents = [
        MatchEventType::Timeout, MatchEventType::BallTouchedReferee,
        MatchEventType::StoppageStart, MatchEventType::StoppageEnd,
        MatchEventType::WaterBreak,
        MatchEventType::MatchStart, MatchEventType::FirstHalfEnd,
        MatchEventType::SecondHalfStart, MatchEventType::MatchEnd,
    ];

    foreach ($neutralEvents as $event) {
        expect($event->scope())->toBe(MatchEventScope::Neutral);
    }
});

test('only timeout allows optional team among neutrals', function () {
    expect(MatchEventType::Timeout->allowsOptionalTeam())->toBeTrue();

    $strictNeutrals = [
        MatchEventType::BallTouchedReferee, MatchEventType::StoppageStart,
        MatchEventType::StoppageEnd, MatchEventType::WaterBreak,
        MatchEventType::MatchStart, MatchEventType::FirstHalfEnd,
        MatchEventType::SecondHalfStart, MatchEventType::MatchEnd,
    ];

    foreach ($strictNeutrals as $event) {
        expect($event->allowsOptionalTeam())->toBeFalse();
    }
});
