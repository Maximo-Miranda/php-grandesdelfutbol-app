<?php

use App\Enums\MatchStatus;

test('it has the correct values', function () {
    expect(MatchStatus::Upcoming->value)->toBe('upcoming')
        ->and(MatchStatus::InProgress->value)->toBe('in_progress')
        ->and(MatchStatus::Completed->value)->toBe('completed')
        ->and(MatchStatus::Cancelled->value)->toBe('cancelled');
});

test('it can be created from value', function () {
    expect(MatchStatus::from('upcoming'))->toBe(MatchStatus::Upcoming)
        ->and(MatchStatus::from('in_progress'))->toBe(MatchStatus::InProgress)
        ->and(MatchStatus::from('completed'))->toBe(MatchStatus::Completed)
        ->and(MatchStatus::from('cancelled'))->toBe(MatchStatus::Cancelled);
});

test('tryFrom returns null for invalid value', function () {
    expect(MatchStatus::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(MatchStatus::Upcoming->label())->toBe('Upcoming')
        ->and(MatchStatus::InProgress->label())->toBe('In Progress')
        ->and(MatchStatus::Completed->label())->toBe('Completed')
        ->and(MatchStatus::Cancelled->label())->toBe('Cancelled');
});
