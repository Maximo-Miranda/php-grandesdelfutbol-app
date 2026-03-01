<?php

use App\Enums\InvitationStatus;

test('it has the correct values', function () {
    expect(InvitationStatus::Pending->value)->toBe('pending')
        ->and(InvitationStatus::Accepted->value)->toBe('accepted');
});

test('it can be created from value', function () {
    expect(InvitationStatus::from('pending'))->toBe(InvitationStatus::Pending)
        ->and(InvitationStatus::from('accepted'))->toBe(InvitationStatus::Accepted);
});

test('tryFrom returns null for invalid value', function () {
    expect(InvitationStatus::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(InvitationStatus::Pending->label())->toBe('Pending')
        ->and(InvitationStatus::Accepted->label())->toBe('Accepted');
});
