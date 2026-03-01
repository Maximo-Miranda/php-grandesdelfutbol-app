<?php

use App\Enums\ClubMemberRole;

test('it has the correct values', function () {
    expect(ClubMemberRole::Owner->value)->toBe('owner')
        ->and(ClubMemberRole::Admin->value)->toBe('admin')
        ->and(ClubMemberRole::Player->value)->toBe('player');
});

test('it can be created from value', function () {
    expect(ClubMemberRole::from('owner'))->toBe(ClubMemberRole::Owner)
        ->and(ClubMemberRole::from('admin'))->toBe(ClubMemberRole::Admin)
        ->and(ClubMemberRole::from('player'))->toBe(ClubMemberRole::Player);
});

test('tryFrom returns null for invalid value', function () {
    expect(ClubMemberRole::tryFrom('invalid'))->toBeNull();
});

test('it has labels', function () {
    expect(ClubMemberRole::Owner->label())->toBe('Owner')
        ->and(ClubMemberRole::Admin->label())->toBe('Admin')
        ->and(ClubMemberRole::Player->label())->toBe('Player');
});
