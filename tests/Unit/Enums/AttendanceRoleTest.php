<?php

use App\Enums\AttendanceRole;

test('it has the correct values', function () {
    expect(AttendanceRole::Pending->value)->toBe('pending')
        ->and(AttendanceRole::Starter->value)->toBe('starter')
        ->and(AttendanceRole::Substitute->value)->toBe('substitute');
});

test('it can be created from value', function () {
    expect(AttendanceRole::from('pending'))->toBe(AttendanceRole::Pending)
        ->and(AttendanceRole::from('starter'))->toBe(AttendanceRole::Starter)
        ->and(AttendanceRole::from('substitute'))->toBe(AttendanceRole::Substitute);
});

test('tryFrom returns null for invalid value', function () {
    expect(AttendanceRole::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(AttendanceRole::Pending->label())->toBe('Pending')
        ->and(AttendanceRole::Starter->label())->toBe('Starter')
        ->and(AttendanceRole::Substitute->label())->toBe('Substitute');
});
