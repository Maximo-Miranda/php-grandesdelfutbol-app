<?php

use App\Enums\ClubMemberStatus;

test('it has the correct values', function () {
    expect(ClubMemberStatus::Pending->value)->toBe('pending')
        ->and(ClubMemberStatus::Approved->value)->toBe('approved');
});

test('it can be created from value', function () {
    expect(ClubMemberStatus::from('pending'))->toBe(ClubMemberStatus::Pending)
        ->and(ClubMemberStatus::from('approved'))->toBe(ClubMemberStatus::Approved);
});

test('tryFrom returns null for invalid value', function () {
    expect(ClubMemberStatus::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(ClubMemberStatus::Pending->label())->toBe('Pending')
        ->and(ClubMemberStatus::Approved->label())->toBe('Approved');
});
