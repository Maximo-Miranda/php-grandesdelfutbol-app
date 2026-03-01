<?php

use App\Enums\AttendanceTeam;

test('it has the correct values', function () {
    expect(AttendanceTeam::A->value)->toBe('a')
        ->and(AttendanceTeam::B->value)->toBe('b');
});

test('it can be created from value', function () {
    expect(AttendanceTeam::from('a'))->toBe(AttendanceTeam::A)
        ->and(AttendanceTeam::from('b'))->toBe(AttendanceTeam::B);
});

test('tryFrom returns null for invalid value', function () {
    expect(AttendanceTeam::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(AttendanceTeam::A->label())->toBe('Team A')
        ->and(AttendanceTeam::B->label())->toBe('Team B');
});
