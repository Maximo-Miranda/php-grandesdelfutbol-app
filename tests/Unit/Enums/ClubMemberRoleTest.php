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

test('rank returns correct hierarchy values', function () {
    expect(ClubMemberRole::Owner->rank())->toBe(2)
        ->and(ClubMemberRole::Admin->rank())->toBe(1)
        ->and(ClubMemberRole::Player->rank())->toBe(0);
});

test('outranks returns true when role has higher rank', function () {
    expect(ClubMemberRole::Owner->outranks(ClubMemberRole::Admin))->toBeTrue()
        ->and(ClubMemberRole::Owner->outranks(ClubMemberRole::Player))->toBeTrue()
        ->and(ClubMemberRole::Admin->outranks(ClubMemberRole::Player))->toBeTrue();
});

test('outranks returns false for equal or lower rank', function () {
    expect(ClubMemberRole::Admin->outranks(ClubMemberRole::Admin))->toBeFalse()
        ->and(ClubMemberRole::Admin->outranks(ClubMemberRole::Owner))->toBeFalse()
        ->and(ClubMemberRole::Player->outranks(ClubMemberRole::Admin))->toBeFalse()
        ->and(ClubMemberRole::Player->outranks(ClubMemberRole::Owner))->toBeFalse()
        ->and(ClubMemberRole::Owner->outranks(ClubMemberRole::Owner))->toBeFalse();
});
