<?php

use App\Enums\AttendanceStatus;

test('it has the correct values', function () {
    expect(AttendanceStatus::Confirmed->value)->toBe('confirmed')
        ->and(AttendanceStatus::Declined->value)->toBe('declined');
});

test('it can be created from value', function () {
    expect(AttendanceStatus::from('confirmed'))->toBe(AttendanceStatus::Confirmed)
        ->and(AttendanceStatus::from('declined'))->toBe(AttendanceStatus::Declined);
});

test('tryFrom returns null for invalid value', function () {
    expect(AttendanceStatus::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(AttendanceStatus::Confirmed->label())->toBe('Confirmed')
        ->and(AttendanceStatus::Declined->label())->toBe('Declined');
});
